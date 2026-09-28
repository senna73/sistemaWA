<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhCostEntry extends Model
{
    public const CATEGORY_EXAM = 'exame_demissional';

    public const CATEGORY_MISS = 'falta_exame';

    public const CATEGORY_MAIL = 'carta_correio';

    public const CATEGORY_AR = 'ar_rastreio';

    public const CATEGORY_MONTHLY = 'mensalidade_cliomed';

    protected $fillable = [
        'offboarding_process_id',
        'collaborator_id',
        'category',
        'city',
        'expected_amount',
        'actual_amount',
        'paid_amount',
        'status',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(OffboardingProcess::class, 'offboarding_process_id');
    }
}
