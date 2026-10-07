<?php

use App\Models\User;
use App\Support\AccessControl;
use App\Support\PopCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function usersScreenActor(bool $superAdmin = false): User
{
    AccessControl::seed();
    Permission::findOrCreate('Lista de usuários');
    Permission::findOrCreate('Atualizar usuários');

    $user = User::factory()->create(['role' => 'admin', 'email' => $superAdmin ? 'super@example.com' : 'staff@example.com']);
    $user->givePermissionTo(['Lista de usuários', 'Atualizar usuários']);

    if ($superAdmin) {
        $user->givePermissionTo(AccessControl::PERMISSION_SUPER_ADMIN);
    }

    return $user->fresh();
}

it('filters the users table by role', function () {
    $actor = usersScreenActor();
    User::factory()->create(['name' => 'Ana RH', 'role' => 'rh']);
    User::factory()->create(['name' => 'Bruno Coord', 'role' => 'coordinator']);

    $this->actingAs($actor)
        ->get(route('users.table', ['role' => 'rh']))
        ->assertOk()
        ->assertSee('Ana RH')
        ->assertDontSee('Bruno Coord');
});

it('lets a super admin change a user role from the list', function () {
    $actor = usersScreenActor(superAdmin: true);
    $target = User::factory()->create(['role' => 'employee']);

    $this->actingAs($actor)
        ->patch(route('users.role', $target->id), ['role' => 'rh'])
        ->assertOk();

    expect($target->fresh()->role)->toBe('rh');
    expect($target->fresh()->hasRole(AccessControl::ROLE_RH))->toBeTrue();
});

it('forbids changing roles without super admin permission', function () {
    $actor = usersScreenActor();
    $target = User::factory()->create(['role' => 'employee']);

    $this->actingAs($actor)
        ->patch(route('users.role', $target->id), ['role' => 'rh'])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe('employee');
});

it('lets a coordinator lose Agenda RH when the checkbox is saved off', function () {
    $actor = usersScreenActor(superAdmin: true);
    $target = User::factory()->create([
        'name' => 'Coord Agenda',
        'email' => 'coord-agenda@example.com',
        'role' => 'coordinator',
    ]);
    AccessControl::applyToUser($target, 'coordinator');

    expect($target->fresh()->can(PopCatalog::PERMISSION_AGENDA))->toBeTrue();

    $permissions = [];
    foreach ($target->fresh()->getDirectPermissions() as $permission) {
        if ($permission->name === PopCatalog::PERMISSION_AGENDA) {
            continue;
        }

        $permissions[$permission->id] = 'on';
    }

    $this->actingAs($actor)
        ->put(route('users.update', $target->id), [
            'name' => 'Coord Agenda',
            'email' => 'coord-agenda@example.com',
            'role' => 'coordinator',
            'permissions' => $permissions,
        ])
        ->assertCreated();

    expect($target->fresh()->can(PopCatalog::PERMISSION_AGENDA))->toBeFalse();
    expect($target->fresh()->can(AccessControl::PERMISSION_WORK))->toBeTrue();
});

it('copies role-granted permissions onto the user so they can be revoked', function () {
    AccessControl::seed();

    $role = Role::findByName(AccessControl::ROLE_COORDINATOR);
    $role->syncPermissions([PopCatalog::PERMISSION_AGENDA, AccessControl::PERMISSION_WORK]);

    $target = User::factory()->create(['role' => 'coordinator']);
    $target->syncRoles([AccessControl::ROLE_COORDINATOR]);
    $target->syncPermissions([]);

    expect($target->fresh()->can(PopCatalog::PERMISSION_AGENDA))->toBeTrue();
    expect($target->getDirectPermissions())->toHaveCount(0);

    AccessControl::seed();

    $target = $target->fresh();

    expect($target->getDirectPermissions()->pluck('name')->all())
        ->toContain(PopCatalog::PERMISSION_AGENDA)
        ->toContain(AccessControl::PERMISSION_WORK);
    expect($role->fresh()->permissions)->toHaveCount(0);
});

it('persists checkbox permissions when updating a user', function () {
    $actor = usersScreenActor(superAdmin: true);
    $target = User::factory()->create([
        'name' => 'Lia',
        'email' => 'lia@example.com',
        'role' => 'leader',
    ]);
    AccessControl::applyToUser($target, 'leader');

    $permission = Permission::findOrCreate('Lista de usuários');

    $this->actingAs($actor)
        ->put(route('users.update', $target->id), [
            'name' => 'Lia Atualizada',
            'email' => 'lia@example.com',
            'role' => 'leader',
            'permissions' => [
                $permission->id => 'on',
            ],
        ])
        ->assertCreated();

    expect($target->fresh()->name)->toBe('Lia Atualizada');
    expect($target->fresh()->hasPermissionTo('Lista de usuários'))->toBeTrue();
});

it('ignores role changes on update unless the actor is super admin', function () {
    $actor = usersScreenActor();
    $target = User::factory()->create([
        'name' => 'Carla',
        'email' => 'carla@example.com',
        'role' => 'employee',
    ]);

    $this->actingAs($actor)
        ->put(route('users.update', $target->id), [
            'name' => 'Carla',
            'email' => 'carla@example.com',
            'role' => 'rh',
        ])
        ->assertCreated();

    expect($target->fresh()->role)->toBe('employee');
});

it('turns existing users into leaders and the two emails into super admin', function () {
    AccessControl::seed();
    $coordinator = User::factory()->create(['role' => 'coordinator', 'email' => 'coord@example.com']);
    $staff = User::factory()->create(['role' => 'rh', 'email' => 'staff@example.com']);
    $dev = User::factory()->create(['role' => 'employee', 'email' => 'dev@dev.com']);
    $anderson = User::factory()->create(['role' => 'employee', 'email' => 'rh.wamerchandising@gmail.com']);

    AccessControl::normalizeExistingUsers();

    expect($coordinator->fresh()->role)->toBe('coordinator');
    expect($staff->fresh()->role)->toBe('rh');
    expect($dev->fresh()->role)->toBe('super_admin');
    expect($dev->fresh()->can(AccessControl::PERMISSION_SUPER_ADMIN))->toBeTrue();
    expect($anderson->fresh()->role)->toBe('super_admin');
    expect($anderson->fresh()->can(AccessControl::PERMISSION_SUPER_ADMIN))->toBeTrue();
});

it('allows creating a user with the email of a deactivated account', function () {
    AccessControl::seed();
    Permission::findOrCreate('Salvar usuários');
    Permission::findOrCreate('Deletar usuários');

    $actor = usersScreenActor();
    $actor->givePermissionTo(['Salvar usuários', 'Deletar usuários']);

    $old = User::factory()->create(['email' => 'annesallex@gmail.com']);

    $this->actingAs($actor)
        ->delete(route('users.destroy', $old->id))
        ->assertCreated();

    expect($old->fresh()->active)->toBeFalse();
    expect($old->fresh()->email)->not->toBe('annesallex@gmail.com');

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Geysane Salles (RH)',
            'email' => 'annesallex@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertCreated();

    expect(User::query()->where('email', 'annesallex@gmail.com')->where('active', true)->exists())->toBeTrue();
});

it('reclaims an email still held by an already inactive user', function () {
    AccessControl::seed();
    Permission::findOrCreate('Salvar usuários');

    $actor = usersScreenActor();
    $actor->givePermissionTo('Salvar usuários');

    User::factory()->create([
        'email' => 'annesallex@gmail.com',
        'active' => false,
    ]);

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Geysane Salles (RH)',
            'email' => 'annesallex@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertCreated();

    expect(User::query()->where('email', 'annesallex@gmail.com')->where('active', true)->count())->toBe(1);
});

it('grants super admin only to the bootstrap email when seeding', function () {
    $bootstrap = User::factory()->create(['email' => AccessControl::SUPER_ADMIN_BOOTSTRAP_EMAIL]);
    $other = User::factory()->create(['email' => 'dev@dev.com']);

    $this->seed(\Database\Seeders\PermissionsSeeder::class);

    expect($bootstrap->fresh()->can(AccessControl::PERMISSION_SUPER_ADMIN))->toBeTrue();
    expect($other->fresh()->can(AccessControl::PERMISSION_SUPER_ADMIN))->toBeFalse();
});
