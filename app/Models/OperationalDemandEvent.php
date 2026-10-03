<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalDemandEvent extends Model
{
    protected $fillable = [
        'operational_demand_id',
        'user_id',
        'event',
        'notes',
    ];

    public function demand(): BelongsTo
    {
        return $this->belongsTo(OperationalDemand::class, 'operational_demand_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
