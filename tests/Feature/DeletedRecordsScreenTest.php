<?php

use App\Models\Collaborator;
use App\Models\User;
use App\Support\AccessControl;
use Spatie\Permission\Models\Permission;

function deletedRecordsActor(): User
{
    AccessControl::seed();
    Permission::findOrCreate('Lista de usuários');
    Permission::findOrCreate('Lista de colaboradores');
    Permission::findOrCreate('Deletar colaboradores');

    $user = User::factory()->create(['role' => 'rh']);
    $user->givePermissionTo(['Lista de usuários', 'Lista de colaboradores', 'Deletar colaboradores']);

    return $user->fresh();
}

it('lists inactive collaborators and hides them from the active table', function () {
    $actor = deletedRecordsActor();
    $active = Collaborator::factory()->create(['name' => 'Ativo Silva', 'active' => true]);
    $deleted = Collaborator::factory()->create(['name' => 'Demitido Souza', 'active' => false]);

    $this->actingAs($actor)
        ->get(route('collaborators.deleted'))
        ->assertOk()
        ->assertSee('Colaboradores apagados');

    $this->actingAs($actor)
        ->get(route('collaborators.deleted.table'))
        ->assertOk()
        ->assertSee('Demitido Souza')
        ->assertDontSee('Ativo Silva');

    $this->actingAs($actor)
        ->get(route('collaborators.table'))
        ->assertOk()
        ->assertSee('Ativo Silva')
        ->assertDontSee('Demitido Souza');

    expect($active->fresh()->active)->toBeTrue();
    expect($deleted->fresh()->active)->toBeFalse();
});

it('lists deactivated users separately from the active list', function () {
    $actor = deletedRecordsActor();
    $active = User::factory()->create(['name' => 'Conta Ativa', 'active' => true]);
    $deleted = User::factory()->create(['name' => 'Conta Apagada', 'active' => false]);

    $this->actingAs($actor)
        ->get(route('users.deleted.table'))
        ->assertOk()
        ->assertSee('Conta Apagada')
        ->assertDontSee('Conta Ativa');

    $this->actingAs($actor)
        ->get(route('users.table'))
        ->assertOk()
        ->assertSee('Conta Ativa')
        ->assertDontSee('Conta Apagada');

    expect($active->fresh()->active)->toBeTrue();
    expect($deleted->fresh()->active)->toBeFalse();
});

it('deactivates the linked user when a collaborator is removed', function () {
    $actor = deletedRecordsActor();
    $collaborator = Collaborator::factory()->create();
    $linked = User::factory()->create([
        'collaborator_id' => $collaborator->id,
        'active' => true,
    ]);

    $this->actingAs($actor)
        ->delete(route('collaborators.destroy', $collaborator->id))
        ->assertOk();

    expect($collaborator->fresh()->active)->toBeFalse();
    expect($linked->fresh()->active)->toBeFalse();
});
