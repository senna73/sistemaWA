<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'intermittent_contract',
        'mobile',
        'group',
        'leave_end_date',
        'uniform_size',
        'uniform_type',
        
        'examined_medical_clinic_id',
    ];

    protected $casts = [
        'leave_end_date' => 'date',
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
    public function cities()
    {
        return $this->belongsToMany(City::class, 'city_has_collaborator');
    }

    public function clinics()
    {
        return $this->hasOne(MedicalClinic::class);
    }

    public function getTargetSectorsAttribute()
    {
        return $this->sectors ?? collect();
    }

    public function workedSections()
    {
        return $this->hasManyThrough(
            Section::class,
            DailyRate::class,
            'collaborator_id',
            'id',
            'id',
            'section_id'
        )->distinct();
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(
            Section::class,
            'daily_rate',
            'collaborator_id',
            'section_id'
        )->distinct();
    }

    public function uniforms(): HasMany
    {
        return $this->hasMany(CollaboratorUniform::class, 'collaborator_id');
    }

}
