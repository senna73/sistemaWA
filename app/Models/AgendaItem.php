<?php

namespace App\Models;

use App\Support\PopCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_DONE = 'done';

    public const STATUS_LATE = 'late';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'title',
        'description',
        'assignee_id',
        'created_by',
        'due_at',
        'priority',
        'type',
        'area',
        'status',
        'recurrence',
        'parent_id',
        'collaborator_id',
        'offboarding_process_id',
        'candidate_id',
        'company_id',
        'payload',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'payload' => 'array',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(OffboardingProcess::class, 'offboarding_process_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function demand(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OperationalDemand::class);
    }

    public function linkedSummary(): string
    {
        $parts = [];
        if ($this->collaborator) {
            $parts[] = $this->collaborator->name;
        }
        if ($this->candidate) {
            $parts[] = 'Candidato: '.$this->candidate->name;
        }
        if ($this->company) {
            $parts[] = $this->company->name;
        }
        if ($this->process) {
            $parts[] = 'Desligamento #'.$this->process->id;
        }

        return implode(' · ', $parts);
    }

    public function typeLabel(): string
    {
        return PopCatalog::agendaTypes()[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return PopCatalog::agendaStatuses()[$this->effectiveStatus()] ?? $this->status;
    }

    public function effectiveStatus(): string
    {
        if (in_array($this->status, [self::STATUS_DONE, self::STATUS_CANCELLED], true)) {
            return $this->status;
        }

        if ($this->due_at instanceof Carbon && $this->due_at->lt(now()) && $this->status !== self::STATUS_DONE) {
            return self::STATUS_LATE;
        }

        return $this->status;
    }
}
