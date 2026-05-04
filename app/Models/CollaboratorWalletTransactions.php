<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollaboratorWalletTransactions extends Model
{
    protected $table = 'collaborator_wallet_transactions';

    protected $fillable = [
        'collaborator_wallet_id',
        'reference_id',
        'balance_before',
        'balance_after',
        'amount',
        'type',
        'description',
        'metadata',
        'ledger_id',
        'occurred_at'
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'metadata'       => 'array',
    ];
    
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CollaboratorWallet::class, 'collaborator_wallet_id');
    }
    // Relacionamento para auditoria com o Livro Razão
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }
}