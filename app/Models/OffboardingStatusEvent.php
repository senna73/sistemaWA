<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffboardingStatusEvent extends Model
{
    protected $fillable = [
        'offboarding_process_id',
        'from_status',
        'to_status',
        'actor_id',
        'notes',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(OffboardingProcess::class, 'offboarding_process_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
