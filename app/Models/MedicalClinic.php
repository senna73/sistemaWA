<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MedicalClinic extends Model
{
    protected $fillable = [
        'name',
        'active',
    ];


    protected $casts = [
        'active' => 'boolean',
    ];


    public static function getActive(): Collection
    {
        return self::where('active', true)->get();
    }
}
