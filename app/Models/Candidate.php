<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    public const STATUS_DOCS = 'documentos';

    public const STATUS_EXAM = 'exame';

    public const STATUS_ASO = 'aso';

    public const STATUS_ACCOUNTING = 'contabilidade';

    public const STATUS_STORE = 'loja';

    public const STATUS_HIRED = 'contratado';

    public const STATUS_REJECTED = 'reprovado';

    public const OPEN_STATUSES = [
        self::STATUS_DOCS,
        self::STATUS_EXAM,
        self::STATUS_ASO,
        self::STATUS_ACCOUNTING,
        self::STATUS_STORE,
    ];

    public const DOCUMENT_QUEUE = [
        ['kind' => 'id', 'label' => 'RG / documento de identificação', 'stage' => 'documentos', 'next' => self::STATUS_EXAM],
        ['kind' => 'exam_proof', 'label' => 'Comprovante de agendamento Cliomed', 'stage' => 'exame', 'next' => self::STATUS_ASO],
        ['kind' => 'aso', 'label' => 'ASO admissional', 'stage' => 'aso', 'next' => self::STATUS_ACCOUNTING],
        ['kind' => 'accounting', 'label' => 'Comprovante enviado à contabilidade', 'stage' => 'contabilidade', 'next' => self::STATUS_ACCOUNTING],
        ['kind' => 'scale', 'label' => 'Print da escala na loja', 'stage' => 'loja', 'next' => self::STATUS_HIRED],
    ];

    protected $fillable = [
        'vacancy_id',
        'company_id',
        'name',
        'job_title',
        'mobile',
        'status',
        'notes',
        'admission_on',
        'inss_registered_at',
        'inss_verified_at',
    ];

    protected $casts = [
        'admission_on' => 'date',
        'inss_registered_at' => 'datetime',
        'inss_verified_at' => 'datetime',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProcessAttachment::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function boardStageKey(): string
    {
        if ($this->status === self::STATUS_ACCOUNTING || ($this->status === self::STATUS_STORE && $this->inss_verified_at === null)) {
            return 'contabilidade';
        }

        return in_array($this->status, self::OPEN_STATUSES, true)
            ? $this->status
            : 'loja';
    }

    public function stageLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DOCS => 'Documentos pessoais',
            self::STATUS_EXAM => 'Exame admissional',
            self::STATUS_ASO => 'ASO → RH',
            self::STATUS_ACCOUNTING => $this->inss_registered_at
                ? 'Verificação do registro no INSS'
                : 'Encaminhar à contabilidade',
            self::STATUS_STORE => 'Cadastro na loja',
            self::STATUS_HIRED => 'Contratado',
            self::STATUS_REJECTED => 'Reprovado',
            default => $this->status,
        };
    }

    /**
     * @return list<array{ok: bool, label: string}>
     */
    public function popSteps(): array
    {
        $this->loadMissing('attachments');
        $kinds = $this->attachments->pluck('kind');

        return [
            ['ok' => $kinds->contains('id'), 'label' => 'Documentos pessoais'],
            ['ok' => $kinds->contains('exam_proof'), 'label' => 'Agendar exame admissional'],
            ['ok' => $kinds->contains('aso'), 'label' => 'ASO → RH'],
            ['ok' => $kinds->contains('accounting'), 'label' => 'Encaminhar à contabilidade'],
            ['ok' => $this->inss_verified_at !== null, 'label' => 'Verificação do registro no INSS'],
            ['ok' => in_array($this->status, [self::STATUS_STORE, self::STATUS_HIRED], true), 'label' => 'Atualizar cadastro na loja'],
            ['ok' => $kinds->contains('scale'), 'label' => 'Primeira escala'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documentQueue(): array
    {
        $this->loadMissing('attachments');
        $byKind = $this->attachments->keyBy('kind');

        return array_map(function (array $item) use ($byKind) {
            $attachment = $byKind->get($item['kind']);
            $item['status'] = $attachment ? 'anexado' : 'pendente';
            $item['src'] = $attachment
                ? route('work.hiring.attachment', [$this, $attachment])
                : null;
            $item['upload_url'] = route('work.hiring.document', $this);

            return $item;
        }, self::DOCUMENT_QUEUE);
    }

    /**
     * @return array<string, mixed>
     */
    public function boardCard(): array
    {
        return [
            'slug' => 'hire-'.$this->id,
            'candidate_id' => $this->id,
            'style' => 'hire-slot',
            'tag' => 'Contratação em andamento',
            'title' => $this->name,
            'name' => $this->name,
            'kind' => 'Contratação',
            'store' => $this->company?->name ?? '—',
            'role' => $this->job_title,
            'stage' => $this->stageLabel(),
            'body' => trim(($this->admission_on ? 'Admissão '.$this->admission_on->format('d/m/Y').' · ' : '').($this->notes ?: $this->stageLabel())),
            'steps' => $this->popSteps(),
            'documents' => $this->documentQueue(),
            'needs_inss_verification' => $this->status === self::STATUS_ACCOUNTING && $this->inss_verified_at === null,
            'verify_url' => route('work.hiring.verify', $this),
        ];
    }
}
