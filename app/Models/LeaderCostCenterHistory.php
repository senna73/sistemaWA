<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderCostCenterHistory extends Model
{
    protected $table = 'leader_cost_center_history';

    protected $fillable = [
        'leader_cost_center_id',
        'ledger_id',
        'amount',
        'type', // enum ['credit', 'debit']
        'description',
        'balance_after'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];


    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(LeaderCostCenter::class, 'leader_cost_center_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }
}