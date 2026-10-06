<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControl
{
    public const PERMISSION_PORTAL = 'Portal do colaborador';

    public const PERMISSION_REQUEST_OWN_DISMISSAL = 'Solicitar demissão';

    public const PERMISSION_REQUEST_OFFBOARDING = 'Solicitar desligamento';

    public const PERMISSION_RH_INBOX = 'Inbox RH';

    public const PERMISSION_MANAGE_OFFBOARDING = 'Gerir desligamentos';

    public const PERMISSION_WORK = 'Acesso Work';

    public const PERMISSION_DIRECTION = 'Minhas Análises Direção';

    public const PERMISSION_RECRUITMENT = 'Recrutamento';

    public const PERMISSION_ACCOUNTING = 'Atendimentos da contabilidade';

    public const ROLE_RH = 'RH';

    public const ROLE_SUPER_ADMIN = 'Super admin';

    public const ROLE_LEADER = 'Líder';

    public const ROLE_COORDINATOR = 'Coordenador';

    public const ROLE_COLLABORATOR = 'Colaborador';

    public const ROLE_EMPLOYEE = 'Funcionário';

    public const ROLE_ACCOUNTING = 'Contabilidade';

    public const PERMISSION_SUPER_ADMIN = 'Super admin';

    public const SUPER_ADMIN_BOOTSTRAP_EMAIL = 'dev@dev.co';

    public static function assignableRoles(): array
    {
        return [
            'super_admin' => 'Super admin',
            'leader' => 'Líder',
            'coordinator' => 'Coordenador',
            'rh' => 'RH',
            'accounting' => 'Contabilidade',
            'employee' => 'Funcionário',
        ];
    }

    public static function roleKeys(): array
    {
        return array_keys(self::assignableRoles());
    }

    public static function seed(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            self::PERMISSION_PORTAL,
            self::PERMISSION_REQUEST_OWN_DISMISSAL,
            self::PERMISSION_REQUEST_OFFBOARDING,
            self::PERMISSION_RH_INBOX,
            self::PERMISSION_MANAGE_OFFBOARDING,
            self::PERMISSION_WORK,
            self::PERMISSION_DIRECTION,
            self::PERMISSION_RECRUITMENT,
            self::PERMISSION_ACCOUNTING,
            PopCatalog::PERMISSION_ACCOUNTING_LIST,
            PopCatalog::PERMISSION_OPEN_DEMAND,
            PopCatalog::PERMISSION_HANDLE_DEMAND,
            PopCatalog::PERMISSION_REVIEW_DEMAND,
            PopCatalog::PERMISSION_AGENDA,
            self::PERMISSION_SUPER_ADMIN,
            'Lista de usuários',
            'Lista de colaboradores',
            'Lista de estabelecimentos',
            'Lista de diárias',
            'Visualizar e inserir informações financeiras nas diárias',
            'Processar boletos e confirmar recebimento',
            'Gerir pagamento de colaboradores e custos',
            'Gestão dos centros de custo',
            'Visualizar livro razão',
            'Acesso aos dados de diárias',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        self::role(self::ROLE_COLLABORATOR)->syncPermissions([
            self::PERMISSION_PORTAL,
            self::PERMISSION_REQUEST_OWN_DISMISSAL,
        ]);

        self::role(self::ROLE_EMPLOYEE)->syncPermissions([
            self::PERMISSION_PORTAL,
            self::PERMISSION_REQUEST_OWN_DISMISSAL,
        ]);

        self::role(self::ROLE_LEADER)->syncPermissions([]);

        self::role(self::ROLE_SUPER_ADMIN)->syncPermissions([
            self::PERMISSION_SUPER_ADMIN,
            self::PERMISSION_RH_INBOX,
            self::PERMISSION_WORK,
            self::PERMISSION_DIRECTION,
            self::PERMISSION_MANAGE_OFFBOARDING,
            self::PERMISSION_REQUEST_OFFBOARDING,
            self::PERMISSION_RECRUITMENT,
            PopCatalog::PERMISSION_ACCOUNTING_LIST,
            PopCatalog::PERMISSION_OPEN_DEMAND,
            PopCatalog::PERMISSION_HANDLE_DEMAND,
            PopCatalog::PERMISSION_REVIEW_DEMAND,
            PopCatalog::PERMISSION_AGENDA,
        ]);

        self::role(self::ROLE_COORDINATOR)->syncPermissions([
            self::PERMISSION_REQUEST_OFFBOARDING,
            self::PERMISSION_WORK,
            PopCatalog::PERMISSION_OPEN_DEMAND,
            PopCatalog::PERMISSION_AGENDA,
        ]);

        self::role(self::ROLE_RH)->syncPermissions([
            self::PERMISSION_RH_INBOX,
            self::PERMISSION_MANAGE_OFFBOARDING,
            self::PERMISSION_REQUEST_OFFBOARDING,
            self::PERMISSION_WORK,
            self::PERMISSION_RECRUITMENT,
            'Lista de colaboradores',
            PopCatalog::PERMISSION_ACCOUNTING_LIST,
            PopCatalog::PERMISSION_HANDLE_DEMAND,
            PopCatalog::PERMISSION_REVIEW_DEMAND,
            PopCatalog::PERMISSION_AGENDA,
        ]);

        self::role(self::ROLE_ACCOUNTING)->syncPermissions([
            self::PERMISSION_WORK,
            self::PERMISSION_ACCOUNTING,
            PopCatalog::PERMISSION_ACCOUNTING_LIST,
        ]);

        self::ensureBootstrapSuperAdmin();
    }

    public static function normalizeExistingUsers(): void
    {
        self::seed();

        User::query()
            ->where('role', '!=', 'coordinator')
            ->update(['role' => 'leader']);

        User::query()
            ->whereIn('email', ['dev@dev.com', 'rh.wamerchandising@gmail.com'])
            ->orderBy('id')
            ->each(function (User $user) {
                self::applyToUser($user, 'super_admin', false);
            });
    }

    public static function ensureBootstrapSuperAdmin(): void
    {
        Permission::findOrCreate(self::PERMISSION_SUPER_ADMIN);

        $user = User::query()
            ->where('email', self::SUPER_ADMIN_BOOTSTRAP_EMAIL)
            ->first();

        if (! $user) {
            return;
        }

        $user->givePermissionTo(self::PERMISSION_SUPER_ADMIN);
    }

    public static function applyToUser(User $user, string $role, bool $syncRolePermissions = true): void
    {
        self::seed();

        $user->role = $role;
        $user->save();

        if ($role === 'leader') {
            $user->syncRoles([self::ROLE_LEADER]);

            return;
        }

        $spatie = self::spatieRoleFor($role);

        if (! $spatie) {
            return;
        }

        $user->syncRoles([$spatie]);

        if ($syncRolePermissions && ! in_array($role, ['admin', 'dev'], true)) {
            $keepSuperAdmin = $user->hasPermissionTo(self::PERMISSION_SUPER_ADMIN);
            $user->syncPermissions(Role::findByName($spatie)->permissions);
            if ($keepSuperAdmin) {
                $user->givePermissionTo(self::PERMISSION_SUPER_ADMIN);
            }
        }
    }

    public static function spatieRoleFor(string $userRole): ?string
    {
        return match ($userRole) {
            'super_admin' => self::ROLE_SUPER_ADMIN,
            'leader' => self::ROLE_LEADER,
            'rh' => self::ROLE_RH,
            'coordinator' => self::ROLE_COORDINATOR,
            'collaborator' => self::ROLE_COLLABORATOR,
            'employee' => self::ROLE_EMPLOYEE,
            'accounting' => self::ROLE_ACCOUNTING,
            default => null,
        };
    }

    private static function role(string $name): Role
    {
        return Role::findOrCreate($name);
    }
}
