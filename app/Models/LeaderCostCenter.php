<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaderCostCenter extends Model
{
    protected $table = 'leader_cost_centers';

    protected $fillable = [
        'leader_id',
        'company_id',
        'name',
        'balance',
    ];


    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class, 'cost_center_id');
    }
    
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}