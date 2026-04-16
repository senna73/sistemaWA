<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ledger extends Model
{
    protected $fillable = [
        'financial_batch_id',
        'collaborator_wallet_id',
        'user_id',
        'cost_center_id',
        'amount',
        'balance_after',
        'is_reversed',
        'reversal_ledger_id',
        'reversed_at',
        'reversal_reason',
        'entry_type',
        'category',
        'description',
        'metadata'
    ];
    
    // Garante que os valores sejam decimais e o JSON funcione
    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'is_reversed' => 'boolean',
        'reversed_at' => 'datetime',
    ];
    
    // O Lote de origem do dinheiro
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FinancialBatches::class, 'financial_batch_id');
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(LeaderCostCenter::class, 'cost_center_id');
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'reversal_ledger_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_reversed', false);
    }

    public function collaboratorWallet(): BelongsTo
    {
        return $this->belongsTo(CollaboratorWallet::class, 'collaborator_wallet_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}