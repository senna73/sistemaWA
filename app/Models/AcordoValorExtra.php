<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcordoValorExtra extends Model
{
    protected $table = 'acordos_valor_extra';

    protected $fillable = [
        'value',
        'collaborator_id',
        'company_id',
        'active'
    ];
    protected $hidden = ['collaborator', 'company'];
    
    protected $appends = ['collaborator_name', 'company_name'];
    
    public function getCollaboratorNameAttribute()
    {
        return $this->collaborator ? $this->collaborator->name : null;
    }

    public function getCompanyNameAttribute()
    {
        return $this->company ? $this->company->name : null;
    }

    public function collaborator()
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
