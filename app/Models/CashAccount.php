<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    protected $fillable = [
        'name',
        'balance',
        'total_added',
        'total_spent',
        'balance',
    ];
}
