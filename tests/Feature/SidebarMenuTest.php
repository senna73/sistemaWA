<?php

use App\Models\User;
use App\Support\AccessControl;

function sidebarUser(string $role): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

it('hides admin sidebar links the coordinator cannot open', function () {
    $user = sidebarUser('coordinator');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('RH Controle')
        ->assertSee('Solicitar desligamento')
        ->assertSee('Uniformes')
        ->assertDontSee('Usuários')
        ->assertDontSee('Estabelecimentos')
        ->assertDontSee('Analytics Financeiro')
        ->assertDontSee('Processamento')
        ->assertDontSee('Ganhos de Colaborador')
        ->assertDontSee('Acompanhamento RH');
});

it('shows only portal links for a collaborator', function () {
    $user = sidebarUser('collaborator');
    $user->collaborator_id = \App\Models\Collaborator::factory()->create()->id;
    $user->save();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Meu cadastro')
        ->assertSee('Meu saldo')
        ->assertDontSee('RH Controle')
        ->assertDontSee('Usuários')
        ->assertDontSee('Administração');
});

it('shows the collaborator list to RH without the direction inbox', function () {
    $user = sidebarUser('rh');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Colaboradores')
        ->assertSee('RH Controle')
        ->assertDontSee('Usuários')
        ->assertDontSee('Acompanhamento RH');
});

it('shows the RH inbox to the general manager', function () {
    $user = sidebarUser('super_admin');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Acompanhamento RH')
        ->assertSee('RH Controle')
        ->assertSee('Cadastro do colaborador')
        ->assertDontSee('Usuários');
});
