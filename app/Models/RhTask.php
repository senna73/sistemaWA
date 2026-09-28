<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhTask extends Model
{
    public const TYPE_INACTIVITY = 'inactivity_check';

    public const TYPE_INACTIVITY_18 = 'inactivity_18';

    public const TYPE_DECISION = 'offboarding_decision';

    public const TYPE_REALLOCATION = 'reallocation_followup';

    public const TYPE_DISMISSAL = 'dismissal_followup';

    public const TYPE_ATENDIMENTO = 'atendimento_rh';

    public const TYPE_ANALISE = 'analise_direcao';

    public const TYPE_EXAME = 'marcacao_exame';

    public const TYPE_CORREIO = 'demissao_correio';

    public const TYPE_CONTABILIDADE = 'aguardando_contabilidade';

    public const TYPE_TRANSFER = 'transferencia_analise';

    public const TYPE_CLINIC = 'clinic_pending';

    public const TYPE_CLIOMED_WEEKLY = 'cliomed_weekly';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'type',
        'status',
        'title',
        'collaborator_id',
        'offboarding_process_id',
        'due_at',
        'completed_by',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(OffboardingProcess::class, 'offboarding_process_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function markDone(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_DONE,
            'completed_by' => $user->id,
            'completed_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function markCancelled(?User $user = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_by' => $user?->id,
            'completed_at' => now(),
        ]);
    }
}
