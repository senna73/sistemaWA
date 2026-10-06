<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class OffboardingProcess extends Model
{
    public const KIND_DISMISSAL = 'dismissal';

    public const KIND_TRANSFER = 'transfer';

    public const KIND_RESIGNATION = 'collaborator_resignation';

    public const KIND_INACTIVITY = 'inactivity_dismissal';

    public const STAGE_ATENDIMENTO_RH = 'atendimento_rh';

    public const STAGE_ANALISE_DIRECAO = 'analise_direcao';

    public const STAGE_MARCACAO_EXAME = 'marcacao_exame';

    public const STAGE_FALTOU_REAGENDAR = 'faltou_reagendar';

    public const STAGE_DISPENSA_EXAME = 'dispensa_exame';

    public const STAGE_DEMISSAO_CORREIO = 'demissao_correio';

    public const STAGE_AGUARDANDO_CONTABILIDADE = 'aguardando_contabilidade';

    public const STAGE_AGUARDANDO_DOCUMENTACAO = 'aguardando_documentacao';

    public const STAGE_TRANSFERENCIA_ANALISE = 'transferencia_analise';

    public const STAGE_TRANSFERENCIA_CONCLUIDA = 'transferencia_concluida';

    public const STAGE_DESLIGADO_CONCLUIDO = 'desligado_concluido';

    public const STAGE_CANCELADO = 'cancelado';

    public const ORIGIN_COORDINATOR = 'coordinator';

    public const ORIGIN_COLLABORATOR = 'collaborator';

    public const LETTER_ANEXADA = 'anexada';

    public const LETTER_PENDENTE = 'pendente';

    public const LETTER_ERRO = 'erro';

    public const CLINIC_CLIOMED = 'cliomed';

    public const CLINIC_CONSERTA = 'conserta';

    public const OPEN_STATUSES = [
        self::STAGE_ATENDIMENTO_RH,
        self::STAGE_ANALISE_DIRECAO,
        self::STAGE_MARCACAO_EXAME,
        self::STAGE_FALTOU_REAGENDAR,
        self::STAGE_DISPENSA_EXAME,
        self::STAGE_DEMISSAO_CORREIO,
        self::STAGE_AGUARDANDO_CONTABILIDADE,
        self::STAGE_AGUARDANDO_DOCUMENTACAO,
        self::STAGE_TRANSFERENCIA_ANALISE,
    ];

    public const DIRECTION_STAGES = [
        self::STAGE_ANALISE_DIRECAO,
        self::STAGE_TRANSFERENCIA_ANALISE,
    ];

    public const DUTY_RH = 'rh';

    public const DUTY_GESTOR = 'gestor';

    public const DUTY_WAIT = 'wait';

    public const TERMINAL_STATUSES = [
        self::STAGE_TRANSFERENCIA_CONCLUIDA,
        self::STAGE_DESLIGADO_CONCLUIDO,
        self::STAGE_CANCELADO,
    ];

    public const LABELS = [
        self::STAGE_ATENDIMENTO_RH => 'Aguardando atendimento do RH',
        self::STAGE_ANALISE_DIRECAO => 'Minhas Análises - Direção',
        self::STAGE_MARCACAO_EXAME => 'Marcação de Exame - Cliomed',
        self::STAGE_FALTOU_REAGENDAR => 'Faltou / Reagendar',
        self::STAGE_DISPENSA_EXAME => 'Dispensa de Exame - Declarações',
        self::STAGE_DEMISSAO_CORREIO => 'Demissões via Correio',
        self::STAGE_AGUARDANDO_CONTABILIDADE => 'Aguardando Contabilidade',
        self::STAGE_AGUARDANDO_DOCUMENTACAO => 'Aguardando Documentação da Contabilidade',
        self::STAGE_TRANSFERENCIA_ANALISE => 'Transferência em Análise',
        self::STAGE_TRANSFERENCIA_CONCLUIDA => 'Transferência concluída',
        self::STAGE_DESLIGADO_CONCLUIDO => 'Desligado / Concluído',
        self::STAGE_CANCELADO => 'Cancelado',
    ];

    public const KIND_LABELS = [
        self::KIND_DISMISSAL => 'DEMISSÃO',
        self::KIND_TRANSFER => 'TRANSFERÊNCIA',
        self::KIND_RESIGNATION => 'PEDIDO DE DEMISSÃO',
        self::KIND_INACTIVITY => 'DEMISSÃO POR INATIVIDADE',
    ];

    protected $fillable = [
        'collaborator_id',
        'requested_by_user_id',
        'origin',
        'kind',
        'status',
        'reason',
        'last_work_day',
        'letter_status',
        'wa_daily_count',
        'inss_daily_count',
        'accounting_registered',
        'last_exam_clinic',
        'tenure_days',
        'direction_lock_conserta',
        'direction_released_at',
        'authorized_daily_correction',
        'docs_received',
        'docs_checked',
        'deactivated_at',
        'blocks_daily_rates',
        'mail_tracking',
        'exam_at',
        'exam_location',
        'exam_status',
        'new_company_id',
        'new_role',
        'transfer_start_date',
        'offered_stores',
        'collaborator_reply',
        'accounting_sent_at',
        'verification_started_at',
        'dossier_path',
        'dossier_archived_at',
        'dossier_retention_until',
        'decided_by_user_id',
        'decided_at',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $process): void {
            if (! $process->collaborator_id) {
                throw ValidationException::withMessages([
                    'collaborator_id' => 'Todo card de demissão precisa de um colaborador vinculado.',
                ]);
            }
        });
    }

    protected $casts = [
        'decided_at' => 'datetime',
        'last_work_day' => 'date',
        'direction_released_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'exam_at' => 'datetime',
        'transfer_start_date' => 'date',
        'accounting_sent_at' => 'datetime',
        'verification_started_at' => 'datetime',
        'dossier_archived_at' => 'datetime',
        'dossier_retention_until' => 'date',
        'accounting_registered' => 'boolean',
        'direction_lock_conserta' => 'boolean',
        'docs_received' => 'boolean',
        'docs_checked' => 'boolean',
        'blocks_daily_rates' => 'boolean',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function newCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'new_company_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OffboardingStatusEvent::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RhTask::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProcessAttachment::class);
    }

    public function costEntries(): HasMany
    {
        return $this->hasMany(RhCostEntry::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function freesHeadcountSlot(): bool
    {
        return $this->isOpen() && in_array($this->kind, [
            self::KIND_DISMISSAL,
            self::KIND_RESIGNATION,
            self::KIND_INACTIVITY,
            self::KIND_TRANSFER,
        ], true);
    }

    public function isDirectionQueue(): bool
    {
        return in_array($this->status, self::DIRECTION_STAGES, true);
    }

    public function duty(): string
    {
        if (! $this->isOpen()) {
            return self::DUTY_WAIT;
        }

        if ($this->isDirectionQueue()) {
            return self::DUTY_GESTOR;
        }

        if (in_array($this->status, [self::STAGE_AGUARDANDO_CONTABILIDADE, self::STAGE_AGUARDANDO_DOCUMENTACAO], true)
            && ! ($this->docs_received && ! $this->docs_checked)) {
            return self::DUTY_WAIT;
        }

        return self::DUTY_RH;
    }

    public function dutyLabel(): string
    {
        return match ($this->duty()) {
            self::DUTY_GESTOR => 'Aprova o Super admin',
            self::DUTY_WAIT => 'Aguardando outra área',
            default => 'Responsabilidade do RH',
        };
    }

    public function blocksDailyRates(): bool
    {
        return (bool) $this->blocks_daily_rates;
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }

    public function cardTitle(): string
    {
        return '['.$this->kindLabel().'] - '.($this->collaborator?->name ?? 'Colaborador');
    }

    public function inssDifference(): ?int
    {
        if ($this->inss_daily_count === null) {
            return null;
        }

        return (int) $this->wa_daily_count - (int) $this->inss_daily_count;
    }

    public function checklist(): array
    {
        return [
            'carta' => $this->kind !== self::KIND_RESIGNATION || $this->letter_status === self::LETTER_ANEXADA || $this->attachments()->where('kind', 'letter')->exists(),
            'inss' => $this->inss_daily_count !== null && $this->inssDifference() === 0,
            'registro' => $this->accounting_registered === true,
            'aso_ou_dispensa' => $this->attachments()->whereIn('kind', ['aso', 'waiver'])->exists() || ($this->tenure_days !== null && $this->tenure_days < 135),
            'email_contabilidade' => $this->accounting_sent_at !== null,
            'dossie_arquivado' => $this->dossier_archived_at !== null,
            'docs_conferidos' => $this->docs_received && $this->docs_checked,
            'baixa' => $this->deactivated_at !== null || $this->status === self::STAGE_TRANSFERENCIA_CONCLUIDA,
            'whatsapp' => $this->events()->where('event_type', 'whatsapp_group')->exists(),
            'docs_enviados' => $this->events()->where('event_type', 'final_docs')->exists(),
        ];
    }

    public function awaitsRh(): bool
    {
        return $this->status === self::STAGE_ATENDIMENTO_RH && $this->verification_started_at === null;
    }

    public function tenureDays(): int
    {
        return (int) ($this->tenure_days ?? $this->collaborator?->tenureDays() ?? 0);
    }

    public function examWaived(): bool
    {
        return $this->tenureDays() < \App\Services\Rh\OffboardingService::TENURE_EXAM_DAYS;
    }

    /**
     * @return list<array{ok: bool, label: string}>
     */
    public function popSteps(): array
    {
        return match ($this->kind) {
            self::KIND_TRANSFER => $this->transferPop(),
            self::KIND_INACTIVITY => $this->inactivityPop(),
            default => $this->dismissalPop(),
        };
    }

    /**
     * @return list<array{ok: bool, label: string}>
     */
    private function dismissalPop(): array
    {
        $check = $this->checklist();

        return [
            ['ok' => $this->hasLetter(), 'label' => 'Carta a punho'],
            ['ok' => (bool) $check['inss'], 'label' => 'Conferência WA × INSS'],
            ['ok' => (bool) $check['registro'], 'label' => 'Registro na contabilidade'],
            ['ok' => $this->examStepDone(), 'label' => $this->examStepLabel()],
            ['ok' => (bool) $check['docs_conferidos'], 'label' => 'Documentos da contabilidade'],
            ['ok' => (bool) $check['baixa'] && (bool) $check['whatsapp'], 'label' => 'Baixa e WhatsApp'],
            ['ok' => (bool) $check['dossie_arquivado'], 'label' => 'Dossiê arquivado'],
        ];
    }

    /**
     * @return list<array{ok: bool, label: string}>
     */
    private function inactivityPop(): array
    {
        $check = $this->checklist();
        $directionDone = $this->direction_released_at !== null
            || ! in_array($this->status, [self::STAGE_ATENDIMENTO_RH, self::STAGE_ANALISE_DIRECAO], true);

        return [
            ['ok' => true, 'label' => 'Auditoria de inatividade'],
            ['ok' => $directionDone, 'label' => 'Decisão da Direção'],
            ['ok' => $this->examStepDone(), 'label' => $this->examStepLabel()],
            ['ok' => (bool) $check['docs_conferidos'], 'label' => 'Documentos da contabilidade'],
            ['ok' => (bool) $check['baixa'] && (bool) $check['whatsapp'], 'label' => 'Baixa e WhatsApp'],
        ];
    }

    /**
     * @return list<array{ok: bool, label: string}>
     */
    private function transferPop(): array
    {
        return [
            ['ok' => filled($this->offered_stores), 'label' => 'Oferta de lojas'],
            ['ok' => filled($this->collaborator_reply), 'label' => 'Resposta do colaborador'],
            ['ok' => $this->direction_released_at !== null || $this->status === self::STAGE_TRANSFERENCIA_CONCLUIDA, 'label' => 'Decisão da Direção'],
            ['ok' => $this->new_company_id !== null || $this->status === self::STAGE_TRANSFERENCIA_CONCLUIDA, 'label' => 'Troca de loja no cadastro'],
        ];
    }

    public function examStepLabel(): string
    {
        return $this->examWaived()
            ? 'Exame dispensado — menos de 135 dias'
            : 'Marcação de exame / ASO (pago)';
    }

    public function examStepDone(): bool
    {
        if ($this->examWaived()) {
            return true;
        }

        return $this->attachments()->whereIn('kind', ['aso', 'waiver'])->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documentQueue(): array
    {
        $this->loadMissing('attachments');
        $byKind = $this->attachments->keyBy('kind');

        $items = [
            ['kind' => 'letter', 'label' => 'Carta a punho do colaborador', 'stage' => 'carta'],
            ['kind' => 'aso', 'label' => 'ASO demissional', 'stage' => 'exame'],
            ['kind' => 'waiver', 'label' => 'Declaração de dispensa de exame', 'stage' => 'exame'],
            ['kind' => 'mail', 'label' => 'Comprovante de correio / AR', 'stage' => 'correio'],
        ];

        if ($this->examWaived()) {
            $items = array_values(array_filter(
                $items,
                fn (array $item) => ! in_array($item['kind'], ['aso', 'waiver'], true),
            ));
        }

        if ($byKind->has('aso')) {
            $items = array_values(array_filter($items, fn (array $item) => $item['kind'] !== 'waiver'));
        } elseif ($byKind->has('waiver')) {
            $items = array_values(array_filter($items, fn (array $item) => $item['kind'] !== 'aso'));
        }

        return array_map(function (array $item) use ($byKind) {
            $attachment = $byKind->get($item['kind']);
            if ($item['kind'] === 'letter' && ! $attachment && $this->letter_status === self::LETTER_ANEXADA) {
                $item['status'] = 'anexado';
                $item['src'] = null;
                $item['upload_url'] = route('work.offboarding.document', $this);

                return $item;
            }

            $item['status'] = $attachment ? 'anexado' : 'pendente';
            $item['src'] = $attachment
                ? route('work.offboarding.attachment', [$this, $attachment])
                : null;
            $item['upload_url'] = route('work.offboarding.document', $this);

            return $item;
        }, $items);
    }

    public function hasLetter(): bool
    {
        if ($this->letter_status === self::LETTER_ANEXADA) {
            return true;
        }

        $this->loadMissing('attachments');

        return $this->attachments->contains(fn (ProcessAttachment $attachment) => $attachment->kind === 'letter');
    }

    public function boardStageKey(): string
    {
        if ($this->awaitsRh()) {
            return 'atendimento';
        }

        return match ($this->status) {
            self::STAGE_ANALISE_DIRECAO, self::STAGE_TRANSFERENCIA_ANALISE => 'direcao',
            self::STAGE_MARCACAO_EXAME, self::STAGE_FALTOU_REAGENDAR, self::STAGE_DISPENSA_EXAME => 'exame',
            self::STAGE_DEMISSAO_CORREIO => 'correio',
            self::STAGE_AGUARDANDO_CONTABILIDADE, self::STAGE_AGUARDANDO_DOCUMENTACAO => 'contabilidade',
            self::STAGE_DESLIGADO_CONCLUIDO => 'baixa',
            self::STAGE_ATENDIMENTO_RH => match ($this->kind) {
                self::KIND_TRANSFER, self::KIND_INACTIVITY => 'direcao',
                default => $this->hasLetter() ? 'conferencia' : 'carta',
            },
            default => $this->hasLetter() ? 'conferencia' : 'carta',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function boardCard(): array
    {
        $collaborator = $this->collaborator;
        $tenure = $this->tenureDays();
        $daysWithout = $collaborator?->daysWithoutDaily() ?? 0;
        $situation = $this->kindLabel();
        if ($this->origin === self::ORIGIN_COORDINATOR && $this->kind === self::KIND_DISMISSAL) {
            $situation = 'Coordenador solicitar desligamento';
        }

        $duty = $this->duty();
        $colored = in_array($duty, [self::DUTY_RH, self::DUTY_GESTOR], true);
        $lastWorkDay = $this->last_work_day ?? $collaborator?->lastDailyAt();

        return [
            'slug' => 'process-'.$this->id,
            'process_id' => $this->id,
            'collaborator_id' => $this->collaborator_id,
            'style' => 'opening-slot',
            'duty' => $colored ? $duty : null,
            'duty_label' => $colored ? $this->dutyLabel() : null,
            'tag' => $situation,
            'title' => $collaborator?->name ?? 'Colaborador',
            'name' => $collaborator?->name ?? 'Colaborador',
            'kind' => $this->kindLabel(),
            'store' => $collaborator?->homeCompany?->name ?: '—',
            'whatsapp_group' => $collaborator?->group ?: '—',
            'role' => $collaborator?->job_title,
            'stage' => $this->awaitsRh() ? 'Aguardando atendimento do RH' : $this->label(),
            'body' => $tenure.' dias de empresa · '.$daysWithout.' dias sem diária · '
                .($lastWorkDay ? 'Último dia trabalhado '.$lastWorkDay->format('d/m/Y') : 'Sem diária lançada')
                .' · '.$this->examStepLabel(),
            'tenure_days' => $tenure,
            'days_without_daily' => $daysWithout,
            'last_work_on' => $lastWorkDay?->format('d/m/Y'),
            'exam_label' => $this->examStepLabel(),
            'steps' => $this->popSteps(),
            'documents' => $this->awaitsRh() ? [] : $this->documentQueue(),
            'awaiting' => $this->awaitsRh(),
            'start_url' => $this->awaitsRh() ? route('work.offboarding.start', $this) : null,
        ];
    }

    public static function blocksDailyRatesFor(?int $collaboratorId): bool
    {
        if (! $collaboratorId) {
            return false;
        }

        return self::query()
            ->where('collaborator_id', $collaboratorId)
            ->where('blocks_daily_rates', true)
            ->where(function ($query) {
                $query->whereIn('status', self::OPEN_STATUSES)
                    ->orWhere('status', self::STAGE_DESLIGADO_CONCLUIDO);
            })
            ->exists();
    }
}
