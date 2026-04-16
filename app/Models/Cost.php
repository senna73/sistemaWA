<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cost extends Model
{
    protected $fillable = [
        'leader_id',
        'category_id',
        'description',
        'value',
        'date',
        'payment_method',
        'status',
        'paid_at',
        'collaborator_recieve_cost_id',
        'origin_cost_center_id',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'date'    => 'date',
        'value'   => 'decimal:2',
    ];

    
    // O colaborador que recebeu o valor
    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id');
    }

    
    //O líder que registrou o gasto
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    //  Categoria do gasto
    public function category(): BelongsTo
    {
        return $this->belongsTo(CostCategory::class, 'category_id');
    }

    // Centro de custo que movimentou a conta inicialmente
    public function originCostCenter(): BelongsTo
    {
        return $this->belongsTo(LeaderCostCenter::class, 'origin_cost_center_id');
    }

    // Divisões deste custo com outros líderes
    public function shares(): HasMany
    {
        return $this->hasMany(CostLeaderShare::class, 'cost_id');
    }
}