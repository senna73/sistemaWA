<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostLeaderShare extends Model
{
    protected $fillable = [
        'cost_id',
        'leader_id',
        'divided_value',
        'status',
    ];
    
    public function cost()
    {
        return $this->belongsTo(Cost::class, 'cost_id');
    }
    
    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }
}
