<?php

use App\Models\Collaborator;
use App\Models\ConfigTable;
use App\Models\OffboardingProcess;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\RhActivitySettings;

function workHubUser(string $role, ?string $sector = null): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role, 'work_function' => $sector]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

function allowRhActivitiesForSector(string $sector): void
{
    foreach (RhActivitySettings::catalog() as $key => $activity) {
        if (! in_array('rh', $activity['audiences'], true)) {
            continue;
        }

        ConfigTable::putJson(RhActivitySettings::sectorFlag($key), [$sector]);
    }
}

it('shows rh management boards to rh and hides them from the coordinator', function () {
    allowRhActivitiesForSector('dp_assistant');
    $rh = workHubUser('rh', 'dp_assistant');
    $otherSector = workHubUser('rh', 'recruitment');
    $coordinator = workHubUser('coordinator', 'dp_assistant');

    $this->actingAs($rh)
        ->get(route('work.home'))
        ->assertOk()
        ->assertSee('RH Controle')
        ->assertSee('Gestão RH Demissional')
        ->assertSee('Processo de contratação')
        ->assertSee('Demandas a atender')
        ->assertSee('Conferência Cliomed')
        ->assertSee('Liberações de diária')
        ->assertSee('Financeiro e DRE')
        ->assertSee('Operação e uniformes')
        ->assertDontSee('Editar quem vê cada área');

    $this->actingAs($otherSector)
        ->get(route('work.home'))
        ->assertOk()
        ->assertDontSee('Demandas a atender')
        ->assertDontSee('Gestão RH Demissional')
        ->assertDontSee('Conferência Cliomed')
        ->assertDontSee('Financeiro e DRE');

    $this->actingAs($coordinator)
        ->get(route('work.home'))
        ->assertOk()
        ->assertSee('Coordenação')
        ->assertSee('Solicitar desligamento')
        ->assertSee('Liberação de diária')
        ->assertSee('Inatividade da equipe')
        ->assertDontSee('Gestão RH Demissional')
        ->assertDontSee('Processo de contratação')
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
    allowRhActivitiesForSector('dp_assistant');
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::DEMANDS), false);
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::OFFBOARDING), false);
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::OFFBOARDING_REQUEST), false);

    $rh = workHubUser('rh', 'dp_assistant');
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
