<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRateReleaseRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'collaborator_id',
        'company_id',
        'requested_by',
        'reviewed_by',
        'daily_on',
        'window_hours',
        'reason',
        'status',
        'reviewed_at',
        'used_at',
        'notes',
    ];

    protected $casts = [
        'daily_on' => 'date',
        'reviewed_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function covers(Carbon $at): bool
    {
        if ($this->status !== self::STATUS_APPROVED || $this->reviewed_at === null) {
            return false;
        }

        $day = $at->copy()->startOfDay();
        if (! $this->daily_on->equalTo($day) && $this->daily_on->toDateString() !== $day->toDateString()) {
            $expires = $this->reviewed_at->copy()->addHours((int) $this->window_hours);
            if ($at->gt($expires)) {
                return false;
            }
        }

        return $this->daily_on->toDateString() === $day->toDateString()
            || $at->lte($this->reviewed_at->copy()->addHours((int) $this->window_hours));
    }
}
