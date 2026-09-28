<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CliomedWeeklyCheck extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'week_of',
        'wa_count',
        'report_count',
        'status',
        'notes',
        'attachment_path',
        'reconciliation',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'week_of' => 'date',
        'completed_at' => 'datetime',
        'reconciliation' => 'array',
    ];

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
