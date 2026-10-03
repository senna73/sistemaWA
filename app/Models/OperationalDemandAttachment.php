<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OperationalDemandAttachment extends Model
{
    protected $fillable = [
        'operational_demand_id',
        'uploaded_by',
        'path',
        'original_name',
    ];

    public function demand(): BelongsTo
    {
        return $this->belongsTo(OperationalDemand::class, 'operational_demand_id');
    }

    public function existsOnDisk(): bool
    {
        return $this->path && Storage::disk('local')->exists($this->path);
    }
}
