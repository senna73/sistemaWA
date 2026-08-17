<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollaboratorUniform extends Model
{
    protected $table = 'collaborator_uniforms';

    protected $fillable = [
        'collaborator_id',
        'uniform_type_id',
        'uniform_size_id',
        'quantity',
        'delivered_at',
        'observation',
    ];

    public function collaborator()
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function type()
    {
        return $this->belongsTo(UniformType::class, 'uniform_type_id');
    }

    public function size()
    {
        return $this->belongsTo(UniformSize::class, 'uniform_size_id');
    }
}