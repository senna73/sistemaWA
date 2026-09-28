<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicPrice extends Model
{
    protected $table = 'clinic_price_table';

    protected $fillable = [
        'city',
        'clinic',
        'category',
        'amount',
        'effective_from',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
    ];
}
