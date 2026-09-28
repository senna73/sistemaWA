<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InactivityAllowance extends Model
{
    public const REASON_TRAVEL = 'viagem';

    public const REASON_HEALTH = 'saude';

    public const REASON_LEAVE = 'afastamento';

    public const REASON_OTHER = 'outro';

    protected $fillable = [
        'collaborator_id',
        'inactivity_audit_id',
        'reason',
        'starts_on',
        'ends_on',
        'evidence_path',
        'created_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function isActive($at = null): bool
    {
        $at = $at ? \Carbon\Carbon::parse($at)->toDateString() : now()->toDateString();

        return $this->starts_on->toDateString() <= $at && $this->ends_on->toDateString() >= $at;
    }
}
