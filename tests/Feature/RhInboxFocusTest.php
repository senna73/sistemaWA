<?php

use App\Models\Collaborator;
use App\Models\OffboardingProcess;

it('lets the gestor focus acompanhamento rh queues from the hero stats', function () {
    $owner = makeRoleUser('super_admin');
    $rh = makeRoleUser('rh');

    $rhPerson = Collaborator::factory()->create(['name' => 'Fila Com Rh']);
    $gestorPerson = Collaborator::factory()->create(['name' => 'Fila Na Analise']);
    $waitPerson = Collaborator::factory()->create(['name' => 'Fila Outra Area']);

    foreach ([
        [$rhPerson, OffboardingProcess::STAGE_ATENDIMENTO_RH],
        [$gestorPerson, OffboardingProcess::STAGE_ANALISE_DIRECAO],
        [$waitPerson, OffboardingProcess::STAGE_AGUARDANDO_CONTABILIDADE],
    ] as [$collaborator, $status]) {
        OffboardingProcess::query()->create([
            'collaborator_id' => $collaborator->id,
            'requested_by_user_id' => $rh->id,
            'kind' => OffboardingProcess::KIND_DISMISSAL,
            'origin' => OffboardingProcess::ORIGIN_COORDINATOR,
            'status' => $status,
        ]);
    }

    $this->actingAs($owner)
        ->get(route('rh.inbox'))
        ->assertOk()
        ->assertSee('Fila Com Rh')
        ->assertSee('Fila Na Analise')
        ->assertSee('Fila Outra Area')
        ->assertSee('Em andamento')
        ->assertSee('Quadro do RH')
        ->assertSee('Demandas a atender');

    $this->actingAs($owner)
        ->get(route('rh.inbox', ['focus' => 'rh']))
        ->assertOk()
        ->assertSee('Fila Com Rh')
        ->assertDontSee('Fila Na Analise')
        ->assertDontSee('Fila Outra Area');

    $this->actingAs($owner)
        ->get(route('rh.inbox', ['focus' => 'gestor']))
        ->assertOk()
        ->assertSee('Fila Na Analise')
        ->assertDontSee('Fila Com Rh')
        ->assertDontSee('Fila Outra Area');

    $this->actingAs($owner)
        ->get(route('rh.inbox', ['focus' => 'wait']))
        ->assertOk()
        ->assertSee('Fila Outra Area')
        ->assertDontSee('Fila Com Rh')
        ->assertDontSee('Fila Na Analise');
});
