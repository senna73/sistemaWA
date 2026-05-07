<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyRate extends Model
{
    protected $table = 'daily_rate';
    
    protected $fillable = [
        'id',
        'collaborator_id',
        'section_id',
        'company_id',
        
        'hourly_rate',
        
        'start',
        'end',
        'total_time',

        'transportation',
        'feeding',
        'addition',
        'pay_amount',
        'leader_comission',
        'earned',
        'profit',
        'inss_paid',
        'tax_paid',
        
        'employee_discount',
        'discount_description',
        
        'active',
        'user_id',
    ];

    protected $casts = [
        'start' => 'date',
        'end' => 'date',

    ];

    public static function getActive()
    {
        return self::query()->where('active', '=', true)->get();
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function collaborator()
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id', 'id');
    }

    public function companySection()
    {
        return $this->belongsTo(CompanyHasSection::class, 'section_id', 'section_id')
            ->whereColumn('company_has_section.company_id', 'company_id');
    }

    // Colaborador do usuário
    public function leader()
    {
        return $this->belongsTo(Collaborator::class, 'user_id', 'id');

    }

}
