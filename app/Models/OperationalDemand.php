<?php

namespace App\Models;

use App\Support\PopCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalDemand extends Model
{
    public const STATUS_AWAITING = 'awaiting';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_REVIEW = 'review';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'collaborator_id',
        'opened_by',
        'assigned_to',
        'agenda_item_id',
        'name',
        'mobile',
        'category',
        'request_text',
        'payload',
        'status',
        'needs_review',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
        'payload' => 'array',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OperationalDemandEvent::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OperationalDemandAttachment::class);
    }

    public function categoryLabel(): string
    {
        return PopCatalog::demandCategories()[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return PopCatalog::demandStatuses()[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status !== self::STATUS_DONE;
    }

    public function needsCadastroApply(): bool
    {
        return isset(PopCatalog::demandApplyFields()[$this->category]);
    }

    public function wasApplied(): bool
    {
        return (bool) (($this->payload ?? [])['applied'] ?? false);
    }

    public function duty(): ?string
    {
        return match ($this->status) {
            self::STATUS_AWAITING, self::STATUS_IN_PROGRESS => 'rh',
            self::STATUS_REVIEW => 'gestor',
            default => null,
        };
    }

    public function dutyLabel(): ?string
    {
        return match ($this->duty()) {
            'rh' => 'RH precisa atender',
            'gestor' => 'Aguardando conferência',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function boardCard(bool $canOperate = false): array
    {
        $duty = $this->duty();
        $snippet = trim(preg_replace('/\s+/', ' ', (string) $this->request_text) ?? '');
        if (mb_strlen($snippet) > 90) {
            $snippet = mb_substr($snippet, 0, 87).'…';
        }

        return [
            'slug' => 'demand-'.$this->id,
            'demand_id' => $this->id,
            'demand' => $this,
            'can_operate' => $canOperate,
            'style' => match ($this->status) {
                self::STATUS_AWAITING => 'opening-slot',
                self::STATUS_IN_PROGRESS => 'hire-slot',
                self::STATUS_REVIEW => 'excess-slot',
                default => 'open-slot',
            },
            'duty' => $duty,
            'duty_label' => $this->dutyLabel(),
            'tag' => $this->statusLabel(),
            'title' => $this->name,
            'name' => $this->name,
            'kind' => $this->categoryLabel(),
            'store' => $this->collaborator?->homeCompany?->name ?: '—',
            'whatsapp_group' => $this->collaborator?->group ?: '—',
            'stage' => $this->statusLabel(),
            'body' => $snippet !== '' ? $snippet : $this->statusLabel(),
            'show_url' => route('demands.show', $this),
        ];
    }
}
