<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collaborator extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'document',
        'pix_key',
        'observation',
        'is_leader',
        'is_supervisor',
        'is_extra',
        'city',
        'intermittent_contract'
    ];

    public static function getActive()
    {
        return self::query()->where('active', '=', true)->get();
    }
    public static function getActiveLeaders()
    {
        return self::query()->where('active', '=', true)->where('is_leader','=', true)->get();
    }
    public function wallet()
    {
        return $this->hasOne(CollaboratorWallet::class);
    }
    public function dailyRates()
    {
        return $this->hasMany(DailyRate::class, 'collaborator_id'); 
    }
}
