<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{

    protected $fillable = [
        'name',
        'document',
        'observation',
        'time_value',
        'category',
        'city',
        'uniforms_laid',
        'chain_of_stores',
        'not_flashing'
    ];
    public static function getAll()
    {
        return self::all();
    }
    public static function getActive()
    {
        return self::query()->where('active', '=', true)->get();

    }
    
    public function companySections()
    {
        return $this->hasMany(CompanyHasSection::class);
    }

    public function costCenter()
    {
        return $this->hasOne(LeaderCostCenter::class, 'company_id');
    }

    public function leader()
    {
        return $this->hasOneThrough(
            User::class,
            LeaderCostCenter::class,
            'company_id',
            'id',
            'id',
            'leader_id'
        );
    }
}
