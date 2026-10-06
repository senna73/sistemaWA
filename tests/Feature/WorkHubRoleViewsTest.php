<?php

use App\Models\Collaborator;
use App\Models\ConfigTable;
use App\Models\OffboardingProcess;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\RhActivitySettings;

function workHubUser(string $role): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

it('shows rh management boards to rh and hides them from the coordinator', function () {
    $rh = workHubUser('rh');
    $coordinator = workHubUser('coordinator');

    $this->actingAs($rh)
        ->get(route('work.home'))
        ->assertOk()
        ->assertSee('RH Controle')
        ->assertSee('Gestão RH Demissional')
        ->assertSee('Demandas a atender')
        ->assertSee('Conferência Cliomed')
        ->assertSee('Liberações de diária')
        ->assertSee('Financeiro e DRE')
        ->assertSee('Operação e uniformes');

    $this->actingAs($coordinator)
        ->get(route('work.home'))
        ->assertOk()
        ->assertSee('Coordenação')
        ->assertSee('Solicitar desligamento')
        ->assertSee('Liberação de diária')
        ->assertSee('Inatividade da equipe')
        ->assertDontSee('Gestão RH Demissional')
        ->assertDontSee('Demandas a atender')
        ->assertDontSee('Conferência Cliomed')
        ->assertDontSee('Financeiro e DRE')
        ->assertDontSee('Operação e uniformes')
        ->assertDontSee('Tarefas abertas');
});

it('blocks coordinator from rh management routes', function () {
    $coordinator = workHubUser('coordinator');
    $rh = workHubUser('rh');
    $other = Collaborator::factory()->create();
    $process = OffboardingProcess::query()->create([
        'collaborator_id' => $other->id,
        'requested_by_user_id' => $rh->id,
        'kind' => OffboardingProcess::KIND_DISMISSAL,
        'origin' => OffboardingProcess::ORIGIN_COORDINATOR,
        'status' => OffboardingProcess::STAGE_ATENDIMENTO_RH,
    ]);

    $this->actingAs($coordinator)
        ->get(route('work.demands'))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.project', 'offboarding'))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.cliomed'))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.project', 'finance'))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.project', 'uniforms'))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.project', 'recruitment'))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.offboarding.show', $process))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.project', ['project' => 'offboarding', 'stage' => 'inactivity']))
        ->assertOk()
        ->assertSee('Inatividade da equipe')
        ->assertDontSee('Aguardando atendimento do RH');
});

it('hides disabled rh activity cards from rh and coordinator screens', function () {
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::DEMANDS), false);
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::OFFBOARDING), false);
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::OFFBOARDING_REQUEST), false);

    $rh = workHubUser('rh');
    $coordinator = workHubUser('coordinator');

    $this->actingAs($rh)
        ->get(route('work.home'))
        ->assertOk()
        ->assertDontSee('Demandas a atender')
        ->assertDontSee('Gestão RH Demissional')
        ->assertSee('Conferência Cliomed');

    $this->actingAs($rh)
        ->get(route('work.demands'))
        ->assertForbidden();

    $this->actingAs($rh)
        ->get(route('work.project', 'offboarding'))
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('work.home'))
        ->assertOk()
        ->assertDontSee('Solicitar desligamento')
        ->assertSee('Liberação de diária')
        ->assertSee('Inatividade da equipe');

    $this->actingAs($coordinator)
        ->get(route('work.request'))
        ->assertForbidden();
});
