<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollaboratorWallet extends Model
{
    protected $table = 'collaborator_wallet';

    protected $fillable = [
        'collaborator_id',
        'balance',
        'total_spent',
        'total_added',
        'occurred_at'
    ];


    protected $casts = [
        'balance' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'total_added' => 'decimal:2',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CollaboratorWalletTransactions::class, 'collaborator_wallet_id');
    }

    
}