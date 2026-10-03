<?php

namespace App\Services\Rh;

use App\Models\AgendaItem;
use App\Models\Collaborator;
use App\Models\OffboardingProcess;
use App\Models\OffboardingStatusEvent;
use App\Models\ProcessAttachment;
use App\Models\RhCostEntry;
use App\Models\RhTask;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OffboardingService
{
    public const TENURE_EXAM_DAYS = 135;

    public function __construct(
        private WhatsAppNotifier $whatsApp,
        private RelatedStaff $relatedStaff,
        private OffboardingDocuments $documents,
        private ClinicCostService $costs,
        private AttendanceNotifier $notifier,
    ) {}

    public function request(
        Collaborator $collaborator,
        User $actor,
        string $origin,
        ?string $notes = null,
        array $extra = [],
    ): OffboardingProcess {
        $kind = $extra['kind'] ?? ($origin === OffboardingProcess::ORIGIN_COLLABORATOR
            ? OffboardingProcess::KIND_RESIGNATION
            : OffboardingProcess::KIND_DISMISSAL);

        return $this->open($collaborator, $actor, $origin, $kind, $notes, $extra);
    }

    public function open(
        Collaborator $collaborator,
        User $actor,
        string $origin,
        string $kind,
        ?string $notes = null,
        array $extra = [],
    ): OffboardingProcess {
        if ($this->openProcessFor($collaborator)) {
            throw ValidationException::withMessages([
                'collaborator' => 'Já existe um processo de desligamento em andamento para este colaborador.',
            ]);
        }

        $collaborator->loadMissing('medicalClinic');

        return DB::transaction(function () use ($collaborator, $actor, $origin, $kind, $notes, $extra) {
            $clinic = $collaborator->clinicSlug();
            $consertaLock = $kind === OffboardingProcess::KIND_DISMISSAL
                && $origin === OffboardingProcess::ORIGIN_COORDINATOR
                && $clinic === OffboardingProcess::CLINIC_CONSERTA;

            $process = OffboardingProcess::create([
                'collaborator_id' => $collaborator->id,
                'requested_by_user_id' => $actor->id,
                'origin' => $origin,
                'kind' => $kind,
                'status' => OffboardingProcess::STAGE_ATENDIMENTO_RH,
                'reason' => $extra['reason'] ?? $notes,
                'last_work_day' => $collaborator->lastDailyAt()?->toDateString(),
                'letter_status' => $extra['letter_status'] ?? ($kind === OffboardingProcess::KIND_RESIGNATION ? OffboardingProcess::LETTER_PENDENTE : null),
                'wa_daily_count' => $collaborator->waDailyCount(),
                'last_exam_clinic' => $clinic,
                'tenure_days' => $collaborator->tenureDays(),
                'direction_lock_conserta' => $consertaLock,
                'notes' => $notes,
            ]);

            $this->recordEvent($process, null, OffboardingProcess::STAGE_ATENDIMENTO_RH, $actor, $notes, 'status');
            $this->syncTask($process, RhTask::TYPE_ATENDIMENTO, $actor);

            if ($kind !== OffboardingProcess::KIND_TRANSFER) {
                $this->spawnGroupRemovalActivity($process, $actor);
            }

            if ($origin === OffboardingProcess::ORIGIN_COLLABORATOR) {
                $this->whatsApp->notifyCollaboratorAndCoordinator($process, $this->relatedStaff->coordinatorsFor($collaborator));
            } else {
                $this->whatsApp->notifyCoordinatorOnly($process, $this->relatedStaff->coordinatorsFor($collaborator));
            }

            if ($kind === OffboardingProcess::KIND_INACTIVITY) {
                $process->update(['blocks_daily_rates' => true]);
            }

            return $process->fresh(['collaborator']);
        });
    }

    public function startVerification(OffboardingProcess $process, User $actor): OffboardingProcess
    {
        $this->assertOpen($process);

        if ($process->verification_started_at) {
            return $process;
        }

        $process->update(['verification_started_at' => now()]);
        $this->recordEvent($process, $process->status, $process->status, $actor, 'Verificação do dossiê iniciada', 'verification');

        if ($process->kind === OffboardingProcess::KIND_INACTIVITY) {
            return $this->moveTo($process->fresh(), OffboardingProcess::STAGE_ANALISE_DIRECAO, $actor, 'Inatividade — decisão da Direção');
        }

        if ($process->kind === OffboardingProcess::KIND_TRANSFER) {
            return $this->moveTo($process->fresh(), OffboardingProcess::STAGE_TRANSFERENCIA_ANALISE, $actor, 'Transferência — oferta e análise');
        }

        return $process->fresh();
    }

    public function markRegistered(OffboardingProcess $process, User $actor, bool $registered, ?UploadedFile $file = null): OffboardingProcess
    {
        if ($file) {
            $this->attach($process, $actor, 'accounting_return', $file);
        }

        $process->update(['accounting_registered' => $registered]);
        $this->recordEvent($process, $process->status, $process->status, $actor, $registered ? 'Registrado no INSS' : 'Registro no INSS pendente', 'inss');

        return $process->fresh();
    }

    public function attach(OffboardingProcess $process, User $actor, string $kind, UploadedFile $file): ProcessAttachment
    {
        $path = $file->store('offboarding/'.$process->id, 'local');

        $attachment = ProcessAttachment::create([
            'offboarding_process_id' => $process->id,
            'kind' => $kind,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $actor->id,
        ]);

        if ($kind === 'letter') {
            $process->update(['letter_status' => OffboardingProcess::LETTER_ANEXADA]);
        }

        $this->recordEvent($process, $process->status, $process->status, $actor, 'Anexo: '.$kind, 'attachment', [
            'kind' => $kind,
            'name' => $file->getClientOriginalName(),
        ]);

        return $attachment;
    }

    public function attachInSequence(OffboardingProcess $process, User $actor, string $kind, UploadedFile $file): OffboardingProcess
    {
        $this->assertOpen($process);
        $process->loadMissing('attachments');

        $pending = collect($process->documentQueue())->first(fn (array $document) => ($document['status'] ?? '') !== 'anexado');
        $allowed = $pending['kind'] ?? null;
        if ($allowed === 'aso' && $kind === 'waiver') {
            $allowed = 'waiver';
        }

        if ($allowed !== $kind) {
            throw ValidationException::withMessages([
                'file' => 'Anexe primeiro: '.($pending['label'] ?? 'o documento da vez').'.',
            ]);
        }

        if ($kind === 'aso') {
            return $this->attachAso($process, $actor, $file);
        }

        if ($kind === 'waiver') {
            return $this->attachWaiverAndContinue($process, $actor, $file);
        }

        $this->attach($process, $actor, $kind, $file);

        return $process->fresh(['attachments', 'collaborator.homeCompany']);
    }

    public function recordConference(
        OffboardingProcess $process,
        User $rh,
        ?int $inssDailyCount,
        ?bool $accountingRegistered,
        ?string $lastExamClinic = null,
        ?string $letterStatus = null,
    ): OffboardingProcess {
        $this->assertOpen($process);

        return DB::transaction(function () use ($process, $rh, $inssDailyCount, $accountingRegistered, $lastExamClinic, $letterStatus) {
            $process->collaborator->loadMissing('medicalClinic');
            $process->update([
                'wa_daily_count' => $process->collaborator->waDailyCount(),
                'inss_daily_count' => $inssDailyCount,
                'accounting_registered' => $accountingRegistered,
                'last_exam_clinic' => $lastExamClinic ?? $process->last_exam_clinic ?? $process->collaborator->clinicSlug(),
                'letter_status' => $letterStatus ?? $process->letter_status,
                'tenure_days' => $process->collaborator->tenureDays(),
            ]);

            $this->recordEvent($process, $process->status, $process->status, $rh, 'Conferência RH', 'conference', [
                'wa' => $process->wa_daily_count,
                'inss' => $inssDailyCount,
                'registered' => $accountingRegistered,
            ]);

            if ($this->needsDirection($process->fresh())) {
                return $this->moveTo($process->fresh(), OffboardingProcess::STAGE_ANALISE_DIRECAO, $rh, 'Encaminhado à Direção');
            }

            return $this->routeAfterClearance($process->fresh(), $rh);
        });
    }

    public function directionDecide(
        OffboardingProcess $process,
        User $actor,
        string $decision,
        ?string $justification = null,
        ?int $correctionQty = null,
    ): OffboardingProcess {
        if (! $process->isDirectionQueue() && $process->status !== OffboardingProcess::STAGE_ANALISE_DIRECAO) {
            throw ValidationException::withMessages(['status' => 'Este card não está em Minhas Análises.']);
        }

        if (in_array($decision, ['release_pending', 'authorize'], true) && blank($justification) && $decision === 'release_pending') {
            throw ValidationException::withMessages(['justification' => 'Justificativa obrigatória para liberar com pendência.']);
        }

        return DB::transaction(function () use ($process, $actor, $decision, $justification, $correctionQty) {
            $process->update([
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'authorized_daily_correction' => $correctionQty ?? $process->authorized_daily_correction,
                'direction_released_at' => in_array($decision, ['release', 'release_pending', 'authorize'], true) ? now() : $process->direction_released_at,
                'direction_lock_conserta' => false,
                'notes' => $justification ?? $process->notes,
            ]);

            $this->recordEvent($process, $process->status, $process->status, $actor, $justification, 'direction', [
                'decision' => $decision,
                'correction' => $correctionQty,
            ]);

            if ($decision === 'return_rh') {
                return $this->moveTo($process, OffboardingProcess::STAGE_ATENDIMENTO_RH, $actor, $justification);
            }

            if ($decision === 'keep') {
                return $process->fresh();
            }

            if ($decision === 'allow_return') {
                $process->update(['blocks_daily_rates' => false]);
                return $this->moveTo($process, OffboardingProcess::STAGE_CANCELADO, $actor, $justification ?? 'Retorno liberado');
            }

            if ($process->kind === OffboardingProcess::KIND_TRANSFER) {
                return $process->fresh();
            }

            if ($process->kind === OffboardingProcess::KIND_INACTIVITY && $decision === 'keep') {
                return $process->fresh();
            }

            return $this->routeAfterClearance($process->fresh(), $actor, true);
        });
    }

    public function scheduleExam(OffboardingProcess $process, User $rh, string $examAt, ?string $location = 'Cliomed'): OffboardingProcess
    {
        $this->assertStatus($process, OffboardingProcess::STAGE_MARCACAO_EXAME, OffboardingProcess::STAGE_FALTOU_REAGENDAR);

        $process->update([
            'exam_at' => $examAt,
            'exam_location' => $location ?: 'Cliomed',
            'exam_status' => 'agendado',
            'status' => OffboardingProcess::STAGE_MARCACAO_EXAME,
        ]);

        $this->costs->ensureExamCost($process);
        $this->recordEvent($process, $process->status, OffboardingProcess::STAGE_MARCACAO_EXAME, $rh, 'Exame agendado', 'exam');
        $this->whatsApp->notifyExamScheduled($process);
        $this->syncTask($process, RhTask::TYPE_EXAME, $rh);

        return $process->fresh();
    }

    public function attachAso(OffboardingProcess $process, User $rh, ?UploadedFile $file = null): OffboardingProcess
    {
        if ($file) {
            $this->attach($process, $rh, 'aso', $file);
        }

        $process->update(['exam_status' => 'realizado']);

        return $this->sendToAccounting($process, $rh, 'ASO anexado');
    }

    public function markExamMissed(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        $this->costs->ensureMissedExamCost($process);

        return $this->moveTo($process, OffboardingProcess::STAGE_FALTOU_REAGENDAR, $rh, $notes ?? 'Faltou ao exame');
    }

    public function attachWaiverAndContinue(OffboardingProcess $process, User $rh, ?UploadedFile $file = null): OffboardingProcess
    {
        if ($file) {
            $this->attach($process, $rh, 'waiver', $file);
        }

        $this->moveTo($process, OffboardingProcess::STAGE_DISPENSA_EXAME, $rh, 'Declaração de dispensa anexada');

        return $this->sendToAccounting($process->fresh(), $rh, 'Dispensa de exame');
    }

    public function moveToMail(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        $this->costs->ensureMailCost($process);

        return $this->moveTo($process, OffboardingProcess::STAGE_DEMISSAO_CORREIO, $rh, $notes ?? 'Sem resposta — via correio');
    }

    public function recordMailProof(OffboardingProcess $process, User $rh, string $tracking, ?UploadedFile $file = null): OffboardingProcess
    {
        $this->assertStatus($process, OffboardingProcess::STAGE_DEMISSAO_CORREIO);

        if ($file) {
            $this->attach($process, $rh, 'mail', $file);
        }

        $process->update(['mail_tracking' => $tracking]);
        $this->recordEvent($process, $process->status, $process->status, $rh, 'Comprovante de correio', 'mail', [
            'tracking' => $tracking,
        ]);

        return $this->sendToAccounting($process->fresh(), $rh, 'Correio comprovado');
    }

    public function sendToAccounting(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        if ($process->direction_lock_conserta && ! $process->direction_released_at) {
            throw ValidationException::withMessages(['status' => 'Aguardando liberação da Direção (Conserta).']);
        }

        $process->update(['accounting_sent_at' => now()]);
        $this->documents->requestDismissalAso($process);
        $this->whatsApp->notifyAccountingHandoff($process);

        $moved = $this->moveTo($process, OffboardingProcess::STAGE_AGUARDANDO_CONTABILIDADE, $rh, $notes);
        $moved->update(['status' => OffboardingProcess::STAGE_AGUARDANDO_DOCUMENTACAO]);
        $this->recordEvent($moved, OffboardingProcess::STAGE_AGUARDANDO_CONTABILIDADE, OffboardingProcess::STAGE_AGUARDANDO_DOCUMENTACAO, $rh, 'Aguardando documentação');
        $this->syncTask($moved, RhTask::TYPE_CONTABILIDADE, $rh);

        $moved = $moved->fresh(['collaborator']);
        $this->notifier->notify(
            $this->notifier->accountingUsers(),
            'Desligamento na contabilidade',
            ($moved->collaborator?->name ?? 'Colaborador').' chegou do sistema para registro e devolução dos documentos.',
            route('work.offboarding.show', $moved),
        );

        return $moved;
    }

    public function markDocs(OffboardingProcess $process, User $rh, bool $received, bool $checked): OffboardingProcess
    {
        $process->update([
            'docs_received' => $received,
            'docs_checked' => $checked,
        ]);
        $this->recordEvent($process, $process->status, $process->status, $rh, 'Documentação da contabilidade', 'docs', [
            'received' => $received,
            'checked' => $checked,
        ]);

        return $process->fresh();
    }

    public function completeDismissal(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        if ($process->kind === OffboardingProcess::KIND_TRANSFER) {
            throw ValidationException::withMessages(['kind' => 'Transferência não gera baixa.']);
        }

        if (! $process->docs_received || ! $process->docs_checked) {
            throw ValidationException::withMessages([
                'docs' => 'A baixa só é liberada com documentação recebida e conferida.',
            ]);
        }

        if ($process->status === OffboardingProcess::STAGE_DEMISSAO_CORREIO && ! $process->mail_tracking) {
            throw ValidationException::withMessages(['mail' => 'Anexe o comprovante de entrega dos correios.']);
        }

        return DB::transaction(function () use ($process, $rh, $notes) {
            try {
                $process->collaborator->update(['active' => false]);
                $process->update([
                    'deactivated_at' => now(),
                    'blocks_daily_rates' => true,
                ]);
            } catch (\Throwable $e) {
                $this->ensureTask($process, RhTask::TYPE_DISMISSAL, 'Pendência de baixa: '.$process->collaborator->name, $rh);

                throw ValidationException::withMessages([
                    'baixa' => 'Falha na baixa automática. Pendência criada para o RH.',
                ]);
            }

            $this->completeGroupRemoval($process->fresh(), $rh);
            $this->documents->archiveAdmissionPack($process);
            $this->documents->notifyEstablishments($process->fresh(['collaborator.homeCompany.coordinator']));
            $this->recordEvent($process, $process->status, OffboardingProcess::STAGE_DESLIGADO_CONCLUIDO, $rh, $notes, 'final_docs');
            $this->whatsApp->notifyFinalDocuments($process);

            return $this->moveTo($process->fresh(), OffboardingProcess::STAGE_DESLIGADO_CONCLUIDO, $rh, $notes);
        });
    }

    public function offerStores(OffboardingProcess $process, User $rh, string $offered, ?string $reply = null): OffboardingProcess
    {
        $process->update([
            'offered_stores' => $offered,
            'collaborator_reply' => $reply,
        ]);
        $this->recordEvent($process, $process->status, $process->status, $rh, 'Lojas oferecidas', 'transfer_offer');

        return $process->fresh();
    }

    public function completeTransfer(
        OffboardingProcess $process,
        User $rh,
        int $companyId,
        ?string $role = null,
        ?string $startDate = null,
    ): OffboardingProcess {
        $this->assertOpen($process);

        return DB::transaction(function () use ($process, $rh, $companyId, $role, $startDate) {
            $process->update([
                'new_company_id' => $companyId,
                'new_role' => $role,
                'transfer_start_date' => $startDate,
                'blocks_daily_rates' => false,
            ]);

            $company = \App\Models\Company::query()->find($companyId);
            $process->collaborator->update([
                'home_company_id' => $companyId,
                'job_title' => $role ?? $process->collaborator->job_title,
                'group' => $company?->name ?? $process->collaborator->group,
            ]);

            $this->whatsApp->notifyGroupMove($process);

            return $this->moveTo($process, OffboardingProcess::STAGE_TRANSFERENCIA_CONCLUIDA, $rh, 'Transferência concluída');
        });
    }

    public function rejectTransfer(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        return $this->moveTo($process, OffboardingProcess::STAGE_TRANSFERENCIA_ANALISE, $rh, $notes ?? 'Sem vaga aceita');
    }

    public function cancel(OffboardingProcess $process, User $actor, ?string $notes = null): OffboardingProcess
    {
        if (! $process->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Este processo já está encerrado.']);
        }

        return DB::transaction(function () use ($process, $actor, $notes) {
            $process->update(['blocks_daily_rates' => false]);
            $this->transition($process, OffboardingProcess::STAGE_CANCELADO, $actor, $notes);
            $process->tasks()->where('status', RhTask::STATUS_PENDING)->each(fn (RhTask $task) => $task->markCancelled($actor));

            return $process->fresh();
        });
    }

    public function resolveInactivity(RhTask $task, User $rh, ?string $notes = null): void
    {
        $this->assertInactivityTask($task);
        $task->markDone($rh, $notes);
    }

    public function openOffboardingFromInactivity(RhTask $task, User $rh, ?string $notes = null): OffboardingProcess
    {
        $this->assertInactivityTask($task);
        $task->loadMissing('collaborator');

        if (! $task->collaborator) {
            throw ValidationException::withMessages([
                'collaborator' => 'Todo card de demissão precisa de um colaborador vinculado.',
            ]);
        }

        $process = $this->open(
            $task->collaborator,
            $rh,
            OffboardingProcess::ORIGIN_COORDINATOR,
            OffboardingProcess::KIND_INACTIVITY,
            $notes ?? 'Aberto a partir de inatividade.'
        );

        $task->markDone($rh, $notes);

        return $process;
    }

    public function openProcessFor(Collaborator $collaborator): ?OffboardingProcess
    {
        return OffboardingProcess::query()
            ->where('collaborator_id', $collaborator->id)
            ->whereIn('status', OffboardingProcess::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }

    public function decideReallocate(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        $process->update(['kind' => OffboardingProcess::KIND_TRANSFER]);

        return $this->moveTo($process, OffboardingProcess::STAGE_ATENDIMENTO_RH, $rh, $notes ?? 'Seguir como transferência');
    }

    public function decideDismiss(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        return $this->recordConference($process, $rh, $process->inss_daily_count, $process->accounting_registered);
    }

    public function completeReallocation(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        if (! $process->new_company_id) {
            throw ValidationException::withMessages(['company' => 'Informe a nova loja.']);
        }

        return $this->completeTransfer($process, $rh, (int) $process->new_company_id, $process->new_role, optional($process->transfer_start_date)?->toDateString());
    }

    public function proceedToDismissal(OffboardingProcess $process, User $rh, ?string $notes = null): OffboardingProcess
    {
        $process->update(['kind' => OffboardingProcess::KIND_DISMISSAL]);

        return $this->routeAfterClearance($process, $rh, true);
    }

    private function routeAfterClearance(OffboardingProcess $process, User $actor, bool $fromDirection = false): OffboardingProcess
    {
        if ($process->direction_lock_conserta && ! $fromDirection && ! $process->direction_released_at) {
            return $this->moveTo($process, OffboardingProcess::STAGE_ANALISE_DIRECAO, $actor, 'Lock Conserta');
        }

        if ($process->kind === OffboardingProcess::KIND_TRANSFER) {
            return $this->moveTo($process, OffboardingProcess::STAGE_ATENDIMENTO_RH, $actor, 'Conferência ok — contato de transferência');
        }

        if ($process->kind === OffboardingProcess::KIND_INACTIVITY && ! $fromDirection) {
            $process->update(['blocks_daily_rates' => true]);

            return $this->moveTo($process, OffboardingProcess::STAGE_ANALISE_DIRECAO, $actor, 'Desligamento por inatividade — Direção');
        }

        $this->whatsApp->notifyCollaboratorOffboardingStarted($process);

        $tenure = $process->tenure_days ?? $process->collaborator->tenureDays();
        $process->update(['blocks_daily_rates' => true, 'tenure_days' => $tenure]);

        if ($tenure >= self::TENURE_EXAM_DAYS) {
            return $this->moveTo($process, OffboardingProcess::STAGE_MARCACAO_EXAME, $actor, '135 dias ou mais — Cliomed');
        }

        return $this->sendToAccounting($process, $actor, 'Menos de 135 dias — contabilidade');
    }

    private function needsDirection(OffboardingProcess $process): bool
    {
        if ($process->direction_lock_conserta && ! $process->direction_released_at) {
            return true;
        }

        if ($process->kind === OffboardingProcess::KIND_RESIGNATION && in_array($process->letter_status, [OffboardingProcess::LETTER_PENDENTE, OffboardingProcess::LETTER_ERRO], true)) {
            return true;
        }

        if ($process->accounting_registered === false) {
            return true;
        }

        if ($process->inss_daily_count !== null && (int) $process->inss_daily_count !== (int) $process->wa_daily_count) {
            return true;
        }

        return false;
    }

    private function moveTo(OffboardingProcess $process, string $to, User $actor, ?string $notes = null): OffboardingProcess
    {
        return DB::transaction(function () use ($process, $to, $actor, $notes) {
            $this->transition($process, $to, $actor, $notes);
            $this->syncTask($process, $this->taskTypeFor($to), $actor);

            return $process->fresh(['collaborator']);
        });
    }

    private function transition(OffboardingProcess $process, string $to, User $actor, ?string $notes): void
    {
        $from = $process->status;
        $process->update([
            'status' => $to,
            'notes' => $notes ?? $process->notes,
        ]);
        $this->recordEvent($process, $from, $to, $actor, $notes);
    }

    private function recordEvent(
        OffboardingProcess $process,
        ?string $from,
        string $to,
        User $actor,
        ?string $notes,
        string $type = 'status',
        array $payload = [],
    ): void {
        OffboardingStatusEvent::create([
            'offboarding_process_id' => $process->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'notes' => $notes,
            'event_type' => $type,
            'payload' => $payload ?: null,
        ]);
    }

    private function syncTask(OffboardingProcess $process, string $type, User $user): void
    {
        $process->tasks()
            ->where('status', RhTask::STATUS_PENDING)
            ->get()
            ->each(fn (RhTask $task) => $task->markDone($user));

        $this->ensureTask($process, $type, $process->cardTitle(), $user);
    }

    private function ensureTask(OffboardingProcess $process, string $type, string $title, User $user): void
    {
        if (in_array($process->status, OffboardingProcess::TERMINAL_STATUSES, true)) {
            return;
        }

        RhTask::create([
            'type' => $type,
            'status' => RhTask::STATUS_PENDING,
            'title' => $title,
            'collaborator_id' => $process->collaborator_id,
            'offboarding_process_id' => $process->id,
        ]);
    }

    private function taskTypeFor(string $stage): string
    {
        return match ($stage) {
            OffboardingProcess::STAGE_ANALISE_DIRECAO => RhTask::TYPE_ANALISE,
            OffboardingProcess::STAGE_MARCACAO_EXAME, OffboardingProcess::STAGE_FALTOU_REAGENDAR => RhTask::TYPE_EXAME,
            OffboardingProcess::STAGE_DEMISSAO_CORREIO => RhTask::TYPE_CORREIO,
            OffboardingProcess::STAGE_AGUARDANDO_CONTABILIDADE, OffboardingProcess::STAGE_AGUARDANDO_DOCUMENTACAO => RhTask::TYPE_CONTABILIDADE,
            OffboardingProcess::STAGE_TRANSFERENCIA_ANALISE => RhTask::TYPE_TRANSFER,
            default => RhTask::TYPE_ATENDIMENTO,
        };
    }

    public function completeGroupRemoval(OffboardingProcess $process, User $actor): void
    {
        if ($process->events()->where('event_type', 'whatsapp_group')->exists()) {
            $this->closeGroupActivities($process);

            return;
        }

        $collaborator = $process->collaborator;
        $result = $this->whatsApp->removeFromStoreGroups($collaborator);
        if ($collaborator && filled($collaborator->group)) {
            $collaborator->update(['group' => null]);
            $result['cleared_group'] = true;
        }

        $this->recordEvent($process, $process->status, $process->status, $actor, 'Retirada dos grupos WhatsApp e da célula', 'whatsapp_group', $result);
        $this->closeGroupActivities($process);
    }

    private function spawnGroupRemovalActivity(OffboardingProcess $process, User $actor): void
    {
        $agenda = app(AgendaService::class);
        $assignee = $agenda->defaultAssignee($actor);
        if (! $assignee) {
            return;
        }

        $exists = AgendaItem::query()
            ->where('offboarding_process_id', $process->id)
            ->where('type', 'offboarding_groups')
            ->whereNotIn('status', [AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED])
            ->exists();
        if ($exists) {
            return;
        }

        $name = $process->collaborator?->name ?? 'colaborador';
        $agenda->create($actor, [
            'title' => 'Retirar '.$name.' dos grupos (WhatsApp e célula)',
            'description' => 'POP de desligamento: tirar o colaborador dos grupos da loja e da célula interna.',
            'assignee_id' => $assignee->id,
            'type' => 'offboarding_groups',
            'due_at' => now()->addDay(),
            'collaborator_id' => $process->collaborator_id,
            'offboarding_process_id' => $process->id,
            'company_id' => $process->collaborator?->home_company_id,
        ]);
    }

    private function closeGroupActivities(OffboardingProcess $process): void
    {
        AgendaItem::query()
            ->where('offboarding_process_id', $process->id)
            ->where('type', 'offboarding_groups')
            ->whereNotIn('status', [AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED])
            ->update(['status' => AgendaItem::STATUS_DONE]);
    }

    private function assertOpen(OffboardingProcess $process): void
    {
        if (! $process->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Processo encerrado.']);
        }
    }

    private function assertStatus(OffboardingProcess $process, string ...$expected): void
    {
        if (! in_array($process->status, $expected, true)) {
            throw ValidationException::withMessages([
                'status' => 'Esta ação não é válida na fase atual ('.$process->label().').',
            ]);
        }
    }

    private function assertInactivityTask(RhTask $task): void
    {
        if (! in_array($task->type, [RhTask::TYPE_INACTIVITY, RhTask::TYPE_INACTIVITY_18], true) || ! $task->isPending()) {
            throw ValidationException::withMessages([
                'task' => 'Tarefa de inatividade inválida ou já encerrada.',
            ]);
        }
    }
}
