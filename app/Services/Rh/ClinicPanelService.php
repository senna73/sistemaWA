<?php

namespace App\Services\Rh;

use App\Models\ClinicPendingCard;
use App\Models\CliomedWeeklyCheck;
use App\Models\Collaborator;
use App\Models\InactivityAudit;
use App\Models\MedicalClinic;
use App\Models\OffboardingProcess;
use App\Models\RhTask;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClinicPanelService
{
    public function syncPendingCards(): int
    {
        $created = 0;

        Collaborator::query()
            ->where('active', true)
            ->whereNull('examined_medical_clinic_id')
            ->chunkById(100, function ($collaborators) use (&$created) {
                foreach ($collaborators as $collaborator) {
                    $card = ClinicPendingCard::query()->firstOrCreate(
                        ['collaborator_id' => $collaborator->id],
                        ['status' => ClinicPendingCard::STATUS_OPEN]
                    );

                    if ($card->wasRecentlyCreated) {
                        $created++;
                        RhTask::query()->firstOrCreate(
                            [
                                'type' => RhTask::TYPE_CLINIC,
                                'collaborator_id' => $collaborator->id,
                                'status' => RhTask::STATUS_PENDING,
                            ],
                            ['title' => '[CLÍNICA PENDENTE] - '.$collaborator->name]
                        );
                    } elseif ($card->status !== ClinicPendingCard::STATUS_OPEN) {
                        $card->update(['status' => ClinicPendingCard::STATUS_OPEN, 'resolved_at' => null, 'resolved_by' => null]);
                    }
                }
            });

        ClinicPendingCard::query()
            ->where('status', ClinicPendingCard::STATUS_OPEN)
            ->whereHas('collaborator', fn ($q) => $q->whereNotNull('examined_medical_clinic_id'))
            ->get()
            ->each(function (ClinicPendingCard $card) {
                $card->update([
                    'status' => ClinicPendingCard::STATUS_RESOLVED,
                    'resolved_at' => now(),
                ]);
                RhTask::query()
                    ->where('type', RhTask::TYPE_CLINIC)
                    ->where('collaborator_id', $card->collaborator_id)
                    ->where('status', RhTask::STATUS_PENDING)
                    ->update(['status' => RhTask::STATUS_DONE, 'completed_at' => now()]);
            });

        return $created;
    }

    public function resolve(ClinicPendingCard $card, User $rh, int $clinicId): ClinicPendingCard
    {
        return DB::transaction(function () use ($card, $rh, $clinicId) {
            $card->collaborator->update(['examined_medical_clinic_id' => $clinicId]);
            $card->update([
                'status' => ClinicPendingCard::STATUS_RESOLVED,
                'resolved_by' => $rh->id,
                'resolved_at' => now(),
            ]);
            RhTask::query()
                ->where('type', RhTask::TYPE_CLINIC)
                ->where('collaborator_id', $card->collaborator_id)
                ->where('status', RhTask::STATUS_PENDING)
                ->get()
                ->each(fn (RhTask $task) => $task->markDone($rh, 'Clínica regularizada'));

            return $card->fresh();
        });
    }

    public function summary(): array
    {
        $this->syncPendingCards();

        $registered = Collaborator::query()->count();
        $cliomed = $this->countClinic('cliomed');
        $conserta = $this->countClinic('conserta');
        $without = Collaborator::query()->where('active', true)->whereNull('examined_medical_clinic_id')->count();

        $stages = [];
        foreach (OffboardingProcess::LABELS as $status => $label) {
            if (in_array($status, OffboardingProcess::TERMINAL_STATUSES, true)) {
                continue;
            }
            $stages[$status] = OffboardingProcess::query()->where('status', $status)->count();
        }
        $stages['clinic_pending'] = ClinicPendingCard::query()->where('status', ClinicPendingCard::STATUS_OPEN)->count();
        $stages['inactivity'] = InactivityAudit::query()
            ->whereIn('status', [
                InactivityAudit::STATUS_OPEN_18,
                InactivityAudit::STATUS_WATCH,
                InactivityAudit::STATUS_OPEN_25,
            ])
            ->count();

        return [
            'registered' => $registered,
            'cliomed' => $cliomed,
            'conserta' => $conserta,
            'without' => $without,
            'stages' => $stages,
            'monthly_cliomed' => round($cliomed * ClinicCostService::MONTHLY_CLIOMED, 2),
        ];
    }

    public function ensureWeeklyCheck(?\Carbon\Carbon $now = null): CliomedWeeklyCheck
    {
        $now = $now?->copy() ?? now();
        $monday = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();

        $existing = CliomedWeeklyCheck::query()->whereDate('week_of', $monday)->first();
        if ($existing) {
            return $existing;
        }

        return CliomedWeeklyCheck::query()->create([
            'week_of' => $monday,
            'wa_count' => $this->countClinic('cliomed'),
            'status' => CliomedWeeklyCheck::STATUS_OPEN,
        ]);
    }

    public function ingestReport(CliomedWeeklyCheck $check, UploadedFile $file, CliomedReportParser $parser, CliomedReconciler $reconciler): array
    {
        $rows = $parser->parse(
            $file->getRealPath() ?: $file->getPathname(),
            $file->getClientOriginalName()
        );
        $result = $reconciler->compare($rows);
        $path = $file->store('cliomed', 'local');

        $check->update([
            'wa_count' => $this->countClinic('cliomed'),
            'report_count' => $result['report_count'],
            'reconciliation' => $result,
            'attachment_path' => $path,
        ]);

        return $result;
    }

    public function hydrateOkPeople(CliomedWeeklyCheck $check): CliomedWeeklyCheck
    {
        $recon = $check->reconciliation ?? [];
        if ($recon === [] || array_key_exists('ok_people', $recon) || ! $check->attachment_path) {
            return $check;
        }

        $path = Storage::disk('local')->path($check->attachment_path);
        if (! is_file($path)) {
            return $check;
        }

        try {
            $fresh = app(CliomedReconciler::class)->compare(
                app(CliomedReportParser::class)->parse($path, basename($path))
            );
        } catch (\Throwable) {
            return $check;
        }

        $recon['ok'] = $fresh['ok'];
        $recon['ok_people'] = $fresh['ok_people'];
        $check->update(['reconciliation' => $recon]);

        return $check->fresh();
    }

    /**
     * @return array{needs_action: bool, kicker: string, badge: string, tone: string, pending: int}
     */
    public function weeklyState(CliomedWeeklyCheck $check): array
    {
        $pending = $this->pendingInconsistencyCount($check);
        $uploaded = $check->report_count !== null;

        if (! $check->isOpen() && $pending === 0) {
            return [
                'needs_action' => false,
                'kicker' => 'Em dia',
                'badge' => 'Conferida',
                'tone' => 'is-quiet',
                'pending' => 0,
            ];
        }

        if (! $uploaded) {
            return [
                'needs_action' => true,
                'kicker' => 'Conferir esta semana',
                'badge' => 'Pendente',
                'tone' => 'is-week',
                'pending' => 0,
            ];
        }

        if ($pending > 0) {
            return [
                'needs_action' => true,
                'kicker' => 'Inconsistências',
                'badge' => $pending.' para resolver',
                'tone' => 'is-week',
                'pending' => $pending,
            ];
        }

        return [
            'needs_action' => true,
            'kicker' => 'Falta finalizar',
            'badge' => 'Conferir',
            'tone' => 'is-week',
            'pending' => 0,
        ];
    }

    public function pendingInconsistencyCount(CliomedWeeklyCheck $check): int
    {
        return count($this->pendingInconsistencies($check));
    }

    /**
     * @return list<array{bucket: string, title: string, hint: string, items: list<array<string, mixed>>}>
     */
    public function inconsistencyGroups(CliomedWeeklyCheck $check): array
    {
        $recon = $check->reconciliation ?? [];
        $resolved = array_flip($recon['resolved'] ?? []);
        $catalog = [
            'only_report' => ['Só no relatório da Cliomed', 'Não achei este nome no cadastro WA.'],
            'only_system' => ['Só no sistema, clínica Cliomed', 'Está como Cliomed na WA e não veio no relatório.'],
            'wrong_clinic' => ['No relatório, clínica diferente na WA', 'Nome encontrado, mas a clínica cadastrada não é Cliomed.'],
            'inactive_in_report' => ['Inativo na WA e ainda no relatório', 'Cliomed está cobrando alguém já inativo.'],
            'ambiguous' => ['Nome ambíguo', 'Mais de um colaborador pode corresponder.'],
        ];

        $groups = [];
        foreach ($catalog as $bucket => [$title, $hint]) {
            $items = [];
            foreach ($recon[$bucket] ?? [] as $item) {
                $key = $this->inconsistencyKey($bucket, $item);
                if (isset($resolved[$key])) {
                    continue;
                }
                $item['_key'] = $key;
                $items[] = $item;
            }
            if ($items !== []) {
                $groups[] = [
                    'bucket' => $bucket,
                    'title' => $title,
                    'hint' => $hint,
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingInconsistencies(CliomedWeeklyCheck $check): array
    {
        $items = [];
        foreach ($this->inconsistencyGroups($check) as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function resolveInconsistency(CliomedWeeklyCheck $check, string $key): CliomedWeeklyCheck
    {
        $found = null;
        $bucket = null;
        foreach ($this->inconsistencyGroups($check) as $group) {
            foreach ($group['items'] as $item) {
                if (($item['_key'] ?? '') === $key) {
                    $found = $item;
                    $bucket = $group['bucket'];
                    break 2;
                }
            }
        }

        if (! $found || ! $bucket) {
            return $check;
        }

        $this->applyClinicRule($bucket, $found);

        $recon = $check->reconciliation ?? [];
        $resolved = $recon['resolved'] ?? [];
        $resolved[] = $key;
        $recon['resolved'] = array_values(array_unique($resolved));
        $check->update(['reconciliation' => $recon]);

        return $check->fresh();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function applyClinicRule(string $bucket, array $item): void
    {
        if ($bucket === 'inactive_in_report' || $bucket === 'ambiguous') {
            return;
        }

        $collaboratorId = $item['id'] ?? null;
        if (! $collaboratorId) {
            return;
        }

        $clinic = match ($bucket) {
            'only_system' => $this->clinicBySlug(OffboardingProcess::CLINIC_CONSERTA),
            'only_report', 'wrong_clinic' => $this->clinicBySlug(OffboardingProcess::CLINIC_CLIOMED),
            default => null,
        };

        if (! $clinic) {
            return;
        }

        Collaborator::query()->whereKey($collaboratorId)->update([
            'examined_medical_clinic_id' => $clinic->id,
        ]);
    }

    public function clinicBySlug(string $slug): MedicalClinic
    {
        $needle = $slug === OffboardingProcess::CLINIC_CONSERTA ? 'conserta' : 'cliomed';
        $existing = MedicalClinic::query()->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%'])->first();
        if ($existing) {
            return $existing;
        }

        return MedicalClinic::query()->create([
            'name' => $slug === OffboardingProcess::CLINIC_CONSERTA ? 'Conserta' : 'Cliomed',
            'active' => true,
        ]);
    }

    /**
     * @return list<string>
     */
    public function chargingNames(CliomedWeeklyCheck $check): array
    {
        $names = [];
        foreach (($check->reconciliation['inactive_in_report'] ?? []) as $item) {
            $name = trim((string) ($item['name'] ?? $item['report_name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function inconsistencyKey(string $bucket, array $item): string
    {
        $id = (string) ($item['id'] ?? $item['name'] ?? $item['report_name'] ?? '');

        return $bucket.'|'.$id;
    }

    public function completeWeekly(CliomedWeeklyCheck $check, User $rh, ?int $reportCount = null, ?string $notes = null, ?string $path = null): CliomedWeeklyCheck
    {
        $check->update([
            'wa_count' => $this->countClinic('cliomed'),
            'report_count' => $reportCount ?? $check->report_count ?? 0,
            'notes' => $notes ?? $check->notes,
            'attachment_path' => $path ?? $check->attachment_path,
            'status' => CliomedWeeklyCheck::STATUS_DONE,
            'completed_by' => $rh->id,
            'completed_at' => now(),
        ]);

        return $check->fresh();
    }

    private function countClinic(string $needle): int
    {
        $ids = MedicalClinic::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return Collaborator::query()->whereIn('examined_medical_clinic_id', $ids)->count();
    }
}
