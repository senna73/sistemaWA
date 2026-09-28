<?php

namespace App\Models;

use App\Support\Document;
use App\Support\PersonName;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Collaborator extends Model
{
    use HasFactory;

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
        'created_at',
        'updated_at',
        'examined_medical_clinic_id',
        'active',
        'hired_at',
        'job_title',
        'home_company_id',
    ];

    protected $casts = [
        'leave_end_date' => 'date',
        'active' => 'boolean',
        'hired_at' => 'datetime',
    ];
    
    public static function getActive()
    {
        return self::query()->where('active', '=', true)->get();
    }
    public static function getActiveLeaders()
    {
        return self::query()->where('active', '=', true)->where('is_leader','=', true)->get();
    }

    public const INSS_EFFECTIVE_FROM = '2026-09-21';

    /** Desconto fixo da tabela (INSS + INSS 13º). Não incide sobre ajuda de custo. */
    public const INSS_AMOUNT = 5.93;

    public function shouldDeductInss(): bool
    {
        if (blank($this->created_at)) {
            return false;
        }

        $createdAt = $this->created_at instanceof Carbon
            ? $this->created_at
            : Carbon::parse($this->created_at);

        return $createdAt->gte(Carbon::parse(self::INSS_EFFECTIVE_FROM)->startOfDay());
    }

    public static function inssAmount(bool $shouldDeduct): float
    {
        return $shouldDeduct ? self::INSS_AMOUNT : 0.0;
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return $query->whereRaw('0 = 1');
        }

        $digits = Document::digits($term);
        $tokens = PersonName::tokens($term);

        return $query->where(function (Builder $group) use ($term, $digits, $tokens) {
            if ($tokens !== []) {
                $group->orWhere(function (Builder $nameQuery) use ($tokens) {
                    foreach ($tokens as $token) {
                        $nameQuery->where('name', 'like', '%'.self::likeValue($token).'%');
                    }
                });
            }

            $safeTerm = self::likeValue($term);
            $group->orWhere('document', 'like', '%'.$safeTerm.'%')
                ->orWhere('mobile', 'like', '%'.$safeTerm.'%')
                ->orWhere('pix_key', 'like', '%'.$safeTerm.'%')
                ->orWhere('city', 'like', '%'.$safeTerm.'%');

            if (strlen($digits) >= 4) {
                $likeDigits = '%'.self::likeValue($digits).'%';
                $group->orWhereRaw(self::digitsSql('document').' like ?', [$likeDigits])
                    ->orWhereRaw(self::digitsSql('mobile').' like ?', [$likeDigits])
                    ->orWhereRaw(self::digitsSql('pix_key').' like ?', [$likeDigits]);
            }
        });
    }

    public function searchHint(): string
    {
        return implode(' · ', array_filter([
            $this->document ?: null,
            $this->mobile ?: null,
            $this->city ?: null,
        ]));
    }

    private static function likeValue(string $value): string
    {
        return str_replace(['%', '_'], '', $value);
    }

    private static function digitsSql(string $column): string
    {
        return "replace(replace(replace(replace(replace(replace(replace(coalesce({$column}, ''), '.', ''), '-', ''), '/', ''), ' ', ''), '(', ''), ')', ''), '+', '')";
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

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'collaborator_id');
    }

    public function offboardingProcesses(): HasMany
    {
        return $this->hasMany(OffboardingProcess::class);
    }

    public function rhTasks(): HasMany
    {
        return $this->hasMany(RhTask::class);
    }

    public function medicalClinic(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MedicalClinic::class, 'examined_medical_clinic_id');
    }

    public function homeCompany(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'home_company_id');
    }

    public function inactivityAudits(): HasMany
    {
        return $this->hasMany(InactivityAudit::class);
    }

    public function inactivityAllowances(): HasMany
    {
        return $this->hasMany(InactivityAllowance::class);
    }

    public function hiredAt(): Carbon
    {
        if ($this->hired_at) {
            return $this->hired_at instanceof Carbon ? $this->hired_at : Carbon::parse($this->hired_at);
        }

        return $this->created_at instanceof Carbon ? $this->created_at : Carbon::parse($this->created_at);
    }

    public function tenureDays($at = null): int
    {
        $at = $at ? Carbon::parse($at) : now();

        return (int) $this->hiredAt()->startOfDay()->diffInDays($at->copy()->startOfDay());
    }

    public function daysWithoutDaily($at = null): int
    {
        $at = $at ? Carbon::parse($at) : now();
        $from = $this->lastDailyAt() ?? $this->hiredAt();

        return (int) $from->copy()->startOfDay()->diffInDays($at->copy()->startOfDay());
    }

    public function lastDailyAt(): ?Carbon
    {
        $start = $this->dailyRates()->where('active', true)->max('start');

        return $start ? Carbon::parse($start) : null;
    }

    public function waDailyCount(): int
    {
        return $this->dailyRates()->where('active', true)->count();
    }

    public function clinicSlug(): ?string
    {
        $name = mb_strtolower((string) $this->medicalClinic?->name);

        if ($name === '') {
            return null;
        }

        if (str_contains($name, 'conserta')) {
            return OffboardingProcess::CLINIC_CONSERTA;
        }

        if (str_contains($name, 'cliomed')) {
            return OffboardingProcess::CLINIC_CLIOMED;
        }

        return $name;
    }

    public function hasActiveAllowance($at = null): bool
    {
        $at = $at ? Carbon::parse($at)->toDateString() : now()->toDateString();

        if ($this->leave_end_date && $this->leave_end_date->toDateString() >= $at) {
            return true;
        }

        return $this->inactivityAllowances()
            ->whereDate('starts_on', '<=', $at)
            ->whereDate('ends_on', '>=', $at)
            ->exists();
    }

}
