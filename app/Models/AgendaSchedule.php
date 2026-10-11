<?php

namespace App\Models;

use App\Support\PopCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaSchedule extends Model
{
    protected $fillable = [
        'title',
        'description',
        'work_function',
        'recurrence',
        'anchor_weekday',
        'anchor_day',
        'due_time',
        'priority',
        'effort_hours',
        'active',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'effort_hours' => 'decimal:2',
        'anchor_weekday' => 'integer',
        'anchor_day' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AgendaItem::class);
    }

    public function workFunctionLabel(): string
    {
        return PopCatalog::workFunctions()[$this->work_function] ?? $this->work_function;
    }

    public function recurrenceLabel(): string
    {
        return PopCatalog::scheduleRecurrences()[$this->recurrence] ?? $this->recurrence;
    }

    public function priorityLabel(): string
    {
        return PopCatalog::agendaPriorities()[$this->priority] ?? $this->priority;
    }
}
