<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InactivityAudit extends Model
{
    public const STATUS_OPEN_18 = 'open_18';

    public const STATUS_WATCH = 'watching';

    public const STATUS_OPEN_25 = 'open_25';

    public const STATUS_CLOSED = 'closed';

    public const RESPONSE_SCALE = 'vou_escalar';

    public const RESPONSE_MOVE = 'vou_remanejar';

    public const RESPONSE_NO_VACANCY = 'sem_vaga';

    public const RESPONSE_NO_INTEREST = 'sem_interesse';

    public const RESPONSE_WAITING = 'aguardando_colaborador';

    protected $fillable = [
        'collaborator_id',
        'status',
        'days_without_daily',
        'last_daily_at',
        'coordinator_response',
        'coordinator_notes',
        'scale_date',
        'scale_store',
        'scale_role',
        'responded_by_user_id',
        'responded_at',
        'collaborator_reply',
        'offboarding_process_id',
        'closed_at',
    ];

    protected $casts = [
        'last_daily_at' => 'datetime',
        'scale_date' => 'date',
        'responded_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(OffboardingProcess::class, 'offboarding_process_id');
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(InactivityAllowance::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN_18, self::STATUS_WATCH, self::STATUS_OPEN_25], true);
    }
}
