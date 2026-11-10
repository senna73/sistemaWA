<?php

namespace App\Services\Rh;

use App\Models\AgendaItem;
use App\Models\Collaborator;
use App\Models\InactivityAllowance;
use App\Models\InactivityAudit;
use App\Models\OffboardingProcess;
use App\Models\OffboardingStatusEvent;
use App\Models\RhTask;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppNotifier;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InactiveCollaboratorDetector
{
    public const ALERT_DAYS = 18;

    /** Usado de novo em janeiro/2027, quando o bloco comentado em detect() voltar. */
    public const INACTIVITY_DAYS = 25;

    public const AUTO_DISMISSAL_REASON = 'Mais de 25 dias sem diária.';

    public function __construct(
        private WhatsAppNotifier $whatsApp,
        private RelatedStaff $relatedStaff,
        private OffboardingService $offboarding,
        private AttendanceNotifier $notifier,
    ) {}

    public function detect(?Carbon $now = null): int
    {
        $now = $now?->copy() ?? now();
        $created = 0;

        Collaborator::query()
            ->where('active', true)
            ->whereDoesntHave('offboardingProcesses', function ($query) {
                $query->whereIn('status', OffboardingProcess::OPEN_STATUSES);
            })
            ->chunkById(100, function (Collection $collaborators) use ($now, &$created) {
                foreach ($collaborators as $collaborator) {
                    if ($collaborator->hasActiveAllowance($now)) {
                        continue;
                    }

                    $days = $this->daysWithoutDaily($collaborator, $now);
                    if ($days < self::ALERT_DAYS) {
                        $this->closeIfDailyReturned($collaborator);
                        continue;
                    }

                    // DÉBITO TÉCNICO — pausado até janeiro/2027. Descomentar este bloco em janeiro.
                    // Aos 25 dias sem diária o colaborador era movido sozinho para a zona de demissão.
                    // Enquanto estiver comentado, quem passa de 25 dias segue só no alerta de 18 dias.
                    // if ($days >= self::INACTIVITY_DAYS) {
                    //     $created += $this->open25($collaborator, $days, $now);
                    //     continue;
                    // }

                    $created += $this->open18($collaborator, $days, $now);
                }
            });

        return $created;
    }

    /**
     * Tira da zona de demissão quem entrou pela regra automática de 25 dias sem diária.
     * Quem foi aberto à mão pelo RH (outro motivo) permanece.
     *
     * @return list<string>
     */
    public function revertAutomaticDismissals(): array
    {
        $actor = User::query()->where('role', 'rh')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $actor) {
            return [];
        }

        $note = 'Débito técnico: reversão da entrada automática na zona de demissão (25 dias sem diária). Retomar em janeiro/2027.';
        $names = [];

        $linkedIds = InactivityAudit::query()
            ->where('status', InactivityAudit::STATUS_OPEN_25)
            ->whereNotNull('offboarding_process_id')
            ->pluck('offboarding_process_id');

        $processes = OffboardingProcess::query()
            ->with('collaborator')
            ->where(function ($query) use ($linkedIds) {
                $query->where(function ($auto) {
                    $auto->where('kind', OffboardingProcess::KIND_INACTIVITY)
                        ->where(function ($reason) {
                            $reason->where('reason', self::AUTO_DISMISSAL_REASON)
                                ->orWhere('notes', self::AUTO_DISMISSAL_REASON);
                        });
                });

                if ($linkedIds->isNotEmpty()) {
                    $query->orWhereIn('id', $linkedIds);
                }
            })
            ->where(function ($query) {
                $query->whereIn('status', OffboardingProcess::OPEN_STATUSES)
                    ->orWhere('status', OffboardingProcess::STAGE_DESLIGADO_CONCLUIDO);
            })
            ->get();

        foreach ($processes as $process) {
            DB::transaction(function () use ($process, $actor, $note, &$names) {
                if ($process->isOpen()) {
                    $this->offboarding->cancel($process, $actor, $note);
                } else {
                    $from = $process->status;
                    $process->collaborator?->update(['active' => true]);
                    $process->update([
                        'status' => OffboardingProcess::STAGE_CANCELADO,
                        'blocks_daily_rates' => false,
                        'notes' => $note,
                    ]);
                    OffboardingStatusEvent::create([
                        'offboarding_process_id' => $process->id,
                        'from_status' => $from,
                        'to_status' => OffboardingProcess::STAGE_CANCELADO,
                        'actor_id' => $actor->id,
                        'notes' => $note,
                        'event_type' => 'status',
                    ]);
                    $process->tasks()
                        ->where('status', RhTask::STATUS_PENDING)
                        ->get()
                        ->each(fn (RhTask $task) => $task->markCancelled($actor));
                }

                AgendaItem::query()
                    ->where('offboarding_process_id', $process->id)
                    ->whereNotIn('status', [AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED])
                    ->update(['status' => AgendaItem::STATUS_CANCELLED]);

                $names[] = $process->collaborator?->name ?? ('Colaborador #'.$process->collaborator_id);
            });
        }

        InactivityAudit::query()
            ->with('collaborator')
            ->where('status', InactivityAudit::STATUS_OPEN_25)
            ->get()
            ->each(function (InactivityAudit $audit) use (&$names) {
                $names[] = $audit->collaborator?->name ?? ('Colaborador #'.$audit->collaborator_id);
                $audit->update([
                    'status' => filled($audit->coordinator_response)
                        ? InactivityAudit::STATUS_WATCH
                        : InactivityAudit::STATUS_OPEN_18,
                    'offboarding_process_id' => null,
                ]);
            });

        RhTask::query()
            ->with('collaborator')
            ->where('type', RhTask::TYPE_INACTIVITY)
            ->where('status', RhTask::STATUS_PENDING)
            ->where('title', 'like', '[DEMISSÃO POR INATIVIDADE]%')
            ->get()
            ->each(function (RhTask $task) use ($actor, &$names) {
                $names[] = $task->collaborator?->name ?? ('Colaborador #'.$task->collaborator_id);
                $task->markCancelled($actor);
            });

        return array_values(array_unique($names));
    }

    public function recordCoordinatorResponse(InactivityAudit $audit, User $coordinator, array $data): InactivityAudit
    {
        $response = $data['coordinator_response'];
        $this->assertResponseFields($response, $data);

        $audit->update([
            'coordinator_response' => $response,
            'coordinator_notes' => $data['coordinator_notes'] ?? $data['justification'] ?? null,
            'scale_date' => $data['scale_date'] ?? null,
            'scale_store' => $data['scale_store'] ?? null,
            'scale_role' => $data['scale_role'] ?? null,
            'responded_by_user_id' => $coordinator->id,
            'responded_at' => now(),
            'status' => InactivityAudit::STATUS_WATCH,
        ]);

        if ($response === InactivityAudit::RESPONSE_JUSTIFY) {
            RhTask::query()->firstOrCreate(
                [
                    'type' => RhTask::TYPE_INACTIVITY,
                    'collaborator_id' => $audit->collaborator_id,
                    'status' => RhTask::STATUS_PENDING,
                ],
                [
                    'title' => sprintf('[ANÁLISE 18 DIAS] - %s', $audit->collaborator?->name ?? 'Colaborador'),
                    'notes' => 'justificativa:'.($data['justification'] ?? $data['coordinator_notes'] ?? ''),
                ]
            );
        }

        return $audit->fresh();
    }

    public function registerAllowance(Collaborator $collaborator, User $rh, array $data, ?UploadedFile $evidence = null, ?InactivityAudit $audit = null): InactivityAllowance
    {
        $path = $evidence?->store('inactivity/allowances', 'local');

        $allowance = InactivityAllowance::create([
            'collaborator_id' => $collaborator->id,
            'inactivity_audit_id' => $audit?->id,
            'reason' => $data['reason'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'evidence_path' => $path,
            'created_by' => $rh->id,
        ]);

        $collaborator->update(['leave_end_date' => $data['ends_on']]);

        if ($audit) {
            $audit->update(['status' => InactivityAudit::STATUS_WATCH]);
        }

        return $allowance;
    }

    private function open18(Collaborator $collaborator, int $days, Carbon $now): int
    {
        $open = InactivityAudit::query()
            ->where('collaborator_id', $collaborator->id)
            ->whereIn('status', [InactivityAudit::STATUS_OPEN_18, InactivityAudit::STATUS_WATCH, InactivityAudit::STATUS_OPEN_25])
            ->first();

        if ($open) {
            $open->update(['days_without_daily' => $days, 'last_daily_at' => $collaborator->lastDailyAt()]);

            return 0;
        }

        $audit = InactivityAudit::create([
            'collaborator_id' => $collaborator->id,
            'status' => InactivityAudit::STATUS_OPEN_18,
            'days_without_daily' => $days,
            'last_daily_at' => $collaborator->lastDailyAt(),
        ]);

        RhTask::create([
            'type' => RhTask::TYPE_INACTIVITY_18,
            'status' => RhTask::STATUS_PENDING,
            'title' => sprintf('[INATIVIDADE 18] - %s', $collaborator->name),
            'collaborator_id' => $collaborator->id,
            'notes' => 'audit:'.$audit->id,
        ]);

        $coords = $this->relatedStaff->coordinatorsFor($collaborator);
        if ($coords->isEmpty()) {
            $collaborator->loadMissing('homeCompany');
            if ($collaborator->homeCompany?->coordinator_id) {
                $coords = User::query()->whereKey($collaborator->homeCompany->coordinator_id)->where('active', true)->get();
            }
        }

        $this->whatsApp->notifyInactivity18Coordinator($collaborator, $coords);
        $this->whatsApp->notifyInactivity18Collaborator($collaborator);

        $this->notifier->notify(
            $coords,
            '18 dias sem diária',
            $collaborator->name.' está há '.$days.' dias sem diária. Identifique o motivo.',
            route('work.project', ['project' => 'offboarding', 'stage' => 'inactivity']),
        );

        $collaboratorUsers = User::query()
            ->where('collaborator_id', $collaborator->id)
            ->where('active', true)
            ->get();

        $this->notifier->notify(
            $collaboratorUsers,
            '18 dias sem diária',
            'Você está há '.$days.' dias sem diária. Informe o motivo ao coordenador.',
            route('portal.show'),
        );

        return 1;
    }

    /**
     * DÉBITO TÉCNICO: pausado até janeiro/2027.
     * A chamada em detect() está comentada. Descomentar as duas coisas juntas em janeiro.
     */
    private function open25(Collaborator $collaborator, int $days, Carbon $now): int
    {
        $audit = InactivityAudit::query()
            ->where('collaborator_id', $collaborator->id)
            ->whereIn('status', [InactivityAudit::STATUS_OPEN_18, InactivityAudit::STATUS_WATCH, InactivityAudit::STATUS_OPEN_25])
            ->latest('id')
            ->first();

        if ($audit?->offboarding_process_id) {
            return 0;
        }

        $system = User::query()->where('role', 'rh')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $system) {
            RhTask::create([
                'type' => RhTask::TYPE_INACTIVITY,
                'status' => RhTask::STATUS_PENDING,
                'title' => sprintf('[DEMISSÃO POR INATIVIDADE] - %s', $collaborator->name),
                'collaborator_id' => $collaborator->id,
            ]);
            $this->whatsApp->notifyInactivity($collaborator, $days, $this->relatedStaff->staffRecipients($collaborator));

            return 1;
        }

        $process = $this->offboarding->open(
            $collaborator,
            $system,
            OffboardingProcess::ORIGIN_COORDINATOR,
            OffboardingProcess::KIND_INACTIVITY,
            self::AUTO_DISMISSAL_REASON,
        );

        if ($audit) {
            $audit->update([
                'status' => InactivityAudit::STATUS_OPEN_25,
                'days_without_daily' => $days,
                'offboarding_process_id' => $process->id,
            ]);
        } else {
            InactivityAudit::create([
                'collaborator_id' => $collaborator->id,
                'status' => InactivityAudit::STATUS_OPEN_25,
                'days_without_daily' => $days,
                'last_daily_at' => $collaborator->lastDailyAt(),
                'offboarding_process_id' => $process->id,
            ]);
        }

        RhTask::query()
            ->where('collaborator_id', $collaborator->id)
            ->where('type', RhTask::TYPE_INACTIVITY_18)
            ->where('status', RhTask::STATUS_PENDING)
            ->get()
            ->each(fn (RhTask $task) => $task->markDone($system));

        $this->whatsApp->notifyInactivity($collaborator, $days, $this->relatedStaff->staffRecipients($collaborator));

        return 1;
    }

    private function closeIfDailyReturned(Collaborator $collaborator): void
    {
        InactivityAudit::query()
            ->where('collaborator_id', $collaborator->id)
            ->whereIn('status', [InactivityAudit::STATUS_OPEN_18, InactivityAudit::STATUS_WATCH])
            ->update([
                'status' => InactivityAudit::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

        RhTask::query()
            ->where('collaborator_id', $collaborator->id)
            ->whereIn('type', [RhTask::TYPE_INACTIVITY_18, RhTask::TYPE_INACTIVITY])
            ->where('status', RhTask::STATUS_PENDING)
            ->update([
                'status' => RhTask::STATUS_DONE,
                'completed_at' => now(),
            ]);
    }

    public function daysWithoutDaily(Collaborator $collaborator, Carbon $now): int
    {
        return $collaborator->daysWithoutDaily($now);
    }

    private function assertResponseFields(string $response, array $data): void
    {
        if ($response === InactivityAudit::RESPONSE_JUSTIFY && empty($data['justification']) && empty($data['coordinator_notes'])) {
            throw ValidationException::withMessages(['justification' => 'Informe a justificativa.']);
        }
        if ($response === InactivityAudit::RESPONSE_CONTINUE) {
            return;
        }
        if ($response === InactivityAudit::RESPONSE_SCALE && (empty($data['scale_date']) || empty($data['scale_store']) || empty($data['scale_role']))) {
            throw ValidationException::withMessages(['scale_date' => 'Informe data prevista, loja e função.']);
        }
        if ($response === InactivityAudit::RESPONSE_MOVE && empty($data['scale_store'])) {
            throw ValidationException::withMessages(['scale_store' => 'Informe loja/região sugerida.']);
        }
        if (in_array($response, [InactivityAudit::RESPONSE_NO_VACANCY, InactivityAudit::RESPONSE_NO_INTEREST, InactivityAudit::RESPONSE_WAITING], true) && empty($data['coordinator_notes'])) {
            throw ValidationException::withMessages(['coordinator_notes' => 'Observação obrigatória.']);
        }
    }
}
