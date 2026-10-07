<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Support\AccessControl;
use App\Models\ConfigTable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasRoles, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'collaborator_id',
        'role',
        'mobile',
        'active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public static function getActive()
    {
        return self::query()->where('active', '=', true)->get();
    }

    public static function findByEmail($email)
    {
        return self::query()->where('email', '=', $email);
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->releaseUniqueIdentity();
        $this->save();
    }

    public function releaseUniqueIdentity(): void
    {
        if (! str_starts_with((string) $this->email, 'deleted.')) {
            $this->email = sprintf('deleted.%d.%s@archived.invalid', $this->id, bin2hex(random_bytes(3)));
        }

        $this->collaborator_id = null;
    }

    public static function releaseInactiveConflicts(?string $email, mixed $collaboratorId = null): void
    {
        $email = $email ? strtolower(trim($email)) : null;
        $collaboratorId = $collaboratorId ? (int) $collaboratorId : null;

        if (! $email && ! $collaboratorId) {
            return;
        }

        static::query()
            ->where('active', false)
            ->where(function ($query) use ($email, $collaboratorId) {
                if ($email) {
                    $query->where('email', $email);
                }

                if ($collaboratorId) {
                    $email
                        ? $query->orWhere('collaborator_id', $collaboratorId)
                        : $query->where('collaborator_id', $collaboratorId);
                }
            })
            ->get()
            ->each(function (self $user) {
                $user->releaseUniqueIdentity();
                $user->save();
            });
    }

    public function collaborator()
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id', 'id');
    }

    public function isCollaboratorRole(): bool
    {
        return in_array($this->role, ['employee', 'collaborator'], true);
    }

    public function isRh(): bool
    {
        return $this->role === 'rh';
    }

    public function managesRhWork(): bool
    {
        return $this->isSuperAdmin()
            || $this->isRh()
            || $this->can(AccessControl::PERMISSION_MANAGE_OFFBOARDING);
    }

    public function coordinatorWorkbench(): bool
    {
        return $this->isCoordinator() && ! $this->managesRhWork();
    }

    public function isOwner(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin'
            || $this->can(AccessControl::PERMISSION_SUPER_ADMIN);
    }

    public function seesCollaboratorPortal(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->can(AccessControl::PERMISSION_PORTAL) && (bool) $this->collaborator_id;
    }

    public function seesPortalRequests(): bool
    {
        if ($this->isCoordinator() && ! $this->isSuperAdmin()) {
            return false;
        }

        return $this->seesCollaboratorPortal();
    }

    public function seesPortalEarnings(): bool
    {
        return $this->seesPortalFeature(ConfigTable::PORTAL_EARNINGS);
    }

    public function seesPortalDailyRates(): bool
    {
        return $this->seesPortalFeature(ConfigTable::PORTAL_DAILY_RATES);
    }

    private function seesPortalFeature(string $flag): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->seesCollaboratorPortal()) {
            return false;
        }

        return ConfigTable::enabled($flag);
    }

    public function roleLabel(): string
    {
        $normalized = $this->role;

        return AccessControl::assignableRoles()[$normalized] ?? 'Equipe';
    }

    public function isAccounting(): bool
    {
        return $this->role === 'accounting';
    }

    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
    }

    public function companies()
    {
        return $this->hasManyThrough(
            Company::class,
            LeaderCostCenter::class,
            'leader_id',
            'id',
            'id',
            'company_id'
        );
    }
}
