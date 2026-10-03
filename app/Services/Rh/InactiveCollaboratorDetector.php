<?php

namespace App\Services\Rh;

use App\Models\Collaborator;
use App\Models\InactivityAllowance;
use App\Models\InactivityAudit;
use App\Models\OffboardingProcess;
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

    public const INACTIVITY_DAYS = 25;

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

                    if ($days >= self::INACTIVITY_DAYS) {
                        $created += $this->open25($collaborator, $days, $now);
                        continue;
                    }

                    $created += $this->open18($collaborator, $days, $now);
                }
            });

        return $created;
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
            'Mais de 25 dias sem diária.',
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
