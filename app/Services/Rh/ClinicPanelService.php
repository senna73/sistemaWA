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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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

    public function hasUploadedReport(CliomedWeeklyCheck $check): bool
    {
        return $check->report_count !== null || ! empty($check->reconciliation);
    }

    public function discardWeeklyReport(CliomedWeeklyCheck $check): CliomedWeeklyCheck
    {
        if (! $check->isOpen()) {
            throw new InvalidArgumentException('Só dá para descartar uma conferência ainda aberta.');
        }

        if ($check->attachment_path) {
            Storage::disk('local')->delete($check->attachment_path);
        }

        $check->update([
            'wa_count' => $this->countClinic('cliomed'),
            'report_count' => null,
            'reconciliation' => null,
            'attachment_path' => null,
            'notes' => null,
        ]);

        return $check->fresh();
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
                $item['_bucket'] = $bucket;
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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveInconsistency(CliomedWeeklyCheck $check, string $key, string $action, array $payload = []): CliomedWeeklyCheck
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

        $this->applyManualAction($bucket, $action, $found, $payload);

        $recon = $check->reconciliation ?? [];
        if ($action === 'link') {
            $this->markCollaboratorMatched(
                $recon,
                (int) ($payload['collaborator_id'] ?? 0),
                trim((string) ($payload['name'] ?? $found['name'] ?? ''))
            );
        }
        $resolved = $recon['resolved'] ?? [];
        $resolved[] = $key;
        $recon['resolved'] = array_values(array_unique($resolved));
        $check->update(['reconciliation' => $recon]);

        return $check->fresh();
    }

    /**
     * @return list<string>
     */
    public function allowedActions(string $bucket): array
    {
        return match ($bucket) {
            'only_report' => ['create_in_wa', 'report_only'],
            'only_system' => ['deactivate'],
            'wrong_clinic' => ['set_cliomed', 'leave_clinic'],
            'inactive_in_report' => ['acknowledge', 'reactivate'],
            'ambiguous' => ['link', 'no_match', 'create_in_wa'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $payload
     */
    public function applyManualAction(string $bucket, string $action, array $item, array $payload = []): void
    {
        if (! in_array($action, $this->allowedActions($bucket), true)) {
            throw new InvalidArgumentException('Esta ação não vale para este tipo de pendência.');
        }

        $name = trim((string) ($payload['name'] ?? $item['name'] ?? $item['report_name'] ?? ''));

        match ($action) {
            'create_in_wa' => $this->createFromReport($item, $name),
            'deactivate' => $this->deactivateFromCheck($item, $name),
            'set_cliomed' => $this->setClinicFromCheck($item, OffboardingProcess::CLINIC_CLIOMED, $name),
            'reactivate' => $this->setActiveFromCheck($item, true, $name),
            'link' => $this->linkFromCheck((int) ($payload['collaborator_id'] ?? 0), $name),
            'leave_clinic', 'report_only', 'acknowledge', 'no_match' => $this->maybeRename($item, $name),
            default => throw new InvalidArgumentException('Ação desconhecida.'),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createFromReport(array $item, string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Informe o nome para criar o cadastro na WA.');
        }

        $clinic = $this->clinicBySlug(OffboardingProcess::CLINIC_CLIOMED);
        $bits = array_filter([
            $item['unit'] ?? null,
            $item['sector'] ?? null,
            $item['role'] ?? null,
        ]);

        Collaborator::query()->create([
            'name' => $name,
            'job_title' => $item['role'] ?? null,
            'examined_medical_clinic_id' => $clinic->id,
            'active' => true,
            'observation' => $bits === [] ? 'Criado na conferência Cliomed.' : 'Criado na conferência Cliomed. '.implode(' · ', $bits),
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function deactivateFromCheck(array $item, string $name): void
    {
        $collaborator = $this->collaboratorFromItem($item);
        $this->maybeRenameCollaborator($collaborator, $name);
        $collaborator->active = false;
        $collaborator->save();
        $collaborator->user?->deactivate();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function setClinicFromCheck(array $item, string $slug, string $name): void
    {
        $collaborator = $this->collaboratorFromItem($item);
        $this->maybeRenameCollaborator($collaborator, $name);
        $collaborator->examined_medical_clinic_id = $this->clinicBySlug($slug)->id;
        $collaborator->save();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function setActiveFromCheck(array $item, bool $active, string $name): void
    {
        $collaborator = $this->collaboratorFromItem($item);
        $this->maybeRenameCollaborator($collaborator, $name);
        $collaborator->active = $active;
        $collaborator->save();
    }

    private function linkFromCheck(int $collaboratorId, string $name): void
    {
        if ($collaboratorId < 1) {
            throw new InvalidArgumentException('Escolha o colaborador da WA para vincular.');
        }

        $collaborator = Collaborator::query()->with('user')->findOrFail($collaboratorId);
        $this->maybeRenameCollaborator($collaborator, $name);
        $collaborator->save();
    }

    /**
     * @param  array<string, mixed>  $recon
     */
    private function markCollaboratorMatched(array &$recon, int $id, string $name): void
    {
        if ($id < 1) {
            return;
        }

        foreach (['only_system', 'wrong_clinic', 'inactive_in_report'] as $bucket) {
            $recon[$bucket] = array_values(array_filter(
                $recon[$bucket] ?? [],
                fn (array $item) => (int) ($item['id'] ?? 0) !== $id
            ));
        }

        $okPeople = $recon['ok_people'] ?? [];
        $already = collect($okPeople)->contains(fn (array $row) => (int) ($row['id'] ?? 0) === $id);
        if (! $already) {
            $okPeople[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : (string) $id,
                'report_name' => $name,
            ];
        }
        $recon['ok_people'] = $okPeople;
        $recon['ok'] = count($okPeople);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function maybeRename(array $item, string $name): void
    {
        if (! isset($item['id']) || $name === '') {
            return;
        }

        $this->maybeRenameCollaborator($this->collaboratorFromItem($item), $name);
    }

    private function maybeRenameCollaborator(Collaborator $collaborator, string $name): void
    {
        if ($name !== '' && $name !== $collaborator->name) {
            $collaborator->name = $name;
            $collaborator->save();
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function collaboratorFromItem(array $item): Collaborator
    {
        $id = (int) ($item['id'] ?? 0);
        if ($id < 1) {
            throw new InvalidArgumentException('Esta pendência não aponta para um colaborador da WA.');
        }

        return Collaborator::query()->with('user')->findOrFail($id);
    }

    /**
     * @return Collection<int, Collaborator>
     */
    public function lookupCollaborators(string $term): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Collaborator::query()
            ->with('medicalClinic')
            ->search($term)
            ->orderBy('name')
            ->limit(20)
            ->get();
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
        return $this->namesFromBucket($check, 'inactive_in_report');
    }

    /**
     * @return list<string>
     */
    public function unregisteredNames(CliomedWeeklyCheck $check): array
    {
        return $this->namesFromBucket($check, 'only_report');
    }

    /**
     * @return list<string>
     */
    private function namesFromBucket(CliomedWeeklyCheck $check, string $bucket): array
    {
        $names = [];
        foreach (($check->reconciliation[$bucket] ?? []) as $item) {
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
