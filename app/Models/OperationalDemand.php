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
}
