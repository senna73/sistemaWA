<?php

use App\Models\Collaborator;
use App\Models\OperationalDemand;
use App\Models\User;
use App\Support\AccessControl;

function demandActor(string $role): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

function openDemand(User $opener, string $category, string $text, string $status = OperationalDemand::STATUS_AWAITING): OperationalDemand
{
    return OperationalDemand::query()->create([
        'opened_by' => $opener->id,
        'name' => $opener->name,
        'category' => $category,
        'request_text' => $text,
        'status' => $status,
    ]);
}

it('shows the RH a type board of activities to attend', function () {
    $rh = demandActor('rh');
    $opener = demandActor('collaborator');
    $collaborator = Collaborator::factory()->create();
    $opener->collaborator_id = $collaborator->id;
    $opener->save();

    openDemand($opener->fresh(), 'troca_pix', 'Atualizar chave Pix da loja');
    openDemand($opener->fresh(), 'declaracao', 'Preciso de declaração de vínculo', OperationalDemand::STATUS_DONE);

    $this->actingAs($rh)
        ->get(route('demands.index'))
        ->assertRedirect(route('work.demands'));

    $this->actingAs($rh)
        ->get(route('work.demands'))
        ->assertOk()
        ->assertSee('Atividades a atender')
        ->assertSee('Fila por tipo')
        ->assertSee('Troca de chave Pix')
        ->assertSee('Atualizar chave Pix da loja')
        ->assertSee('Atender')
        ->assertSee('store-board-stack', false)
        ->assertDontSee('Declaração')
        ->assertDontSee('Preciso de declaração de vínculo')
        ->assertDontSee('Somente visualização')
        ->assertDontSee('Nova demanda');
});

it('lets the admin inspect RH demand work without attending', function () {
    $admin = demandActor('super_admin');
    $opener = demandActor('coordinator');
    openDemand($opener, 'problema_grupo', 'Grupo da loja sem retorno');

    $this->actingAs($admin)
        ->get(route('demands.index'))
        ->assertOk()
        ->assertSee('Demandas do RH')
        ->assertSee('Demandas por tipo')
        ->assertSee('Problema no grupo')
        ->assertSee('Grupo da loja sem retorno')
        ->assertSee('Somente visualização')
        ->assertSee('store-board-stack', false)
        ->assertDontSee('>Atender<', false);

    $this->actingAs($admin)
        ->get(route('rh.inbox'))
        ->assertOk()
        ->assertSee('Quadro do RH')
        ->assertSee('Demandas a atender')
        ->assertSee('Gestão RH Demissional');
});

it('keeps a simple list for people who only open demands', function () {
    $owner = demandActor('coordinator');
    $other = demandActor('super_admin');
    openDemand($owner, 'duvida_diaria', 'Minha dúvida de pagamento');
    openDemand($other, 'solicitacao_loja', 'Pedido interno da coordenação');

    $this->actingAs($owner)
        ->get(route('demands.index'))
        ->assertOk()
        ->assertSee('Minhas demandas')
        ->assertSee('Minha dúvida de pagamento')
        ->assertDontSee('Pedido interno da coordenação')
        ->assertDontSee('Fila por tipo')
        ->assertDontSee('Demandas por tipo');
});

it('forbids RH and collaborators from opening the demand form', function () {
    $rh = demandActor('rh');
    $collaborator = demandActor('collaborator');

    $this->actingAs($rh)->get(route('demands.create'))->assertForbidden();
    $this->actingAs($rh)
        ->post(route('demands.store'), [
            'category' => 'outros',
            'request_text' => 'RH tentando abrir',
            'name' => 'RH',
        ])
        ->assertForbidden();

    $this->actingAs($collaborator)->get(route('demands.create'))->assertForbidden();
});

it('lets a coordinator open a whatsapp group transfer for RH', function () {
    $coordinator = demandActor('coordinator');
    $person = Collaborator::factory()->create(['group' => 'Grupo A', 'name' => 'Maria Grupo']);

    $this->actingAs($coordinator)
        ->post(route('demands.store'), [
            'collaborator_id' => $person->id,
            'category' => 'transferencia_grupo',
            'request_text' => 'Mover para o grupo B',
            'payload' => ['group' => 'Grupo B'],
        ])
        ->assertRedirect(route('demands.index'));

    $demand = OperationalDemand::query()->where('collaborator_id', $person->id)->first();
    expect($demand?->category)->toBe('transferencia_grupo');
    expect($demand?->payload['group'] ?? null)->toBe('Grupo B');
    expect($person->fresh()->group)->toBe('Grupo A');
});

it('applies pix from the demand card without leaving the queue', function () {
    $rh = demandActor('rh');
    $coordinator = demandActor('coordinator');
    $person = Collaborator::factory()->create(['pix_key' => 'antiga', 'name' => 'Pix Card']);
    $demand = OperationalDemand::query()->create([
        'opened_by' => $coordinator->id,
        'collaborator_id' => $person->id,
        'name' => $person->name,
        'category' => 'troca_pix',
        'request_text' => 'Nova chave',
        'payload' => ['pix_key' => 'nova-chave'],
        'status' => OperationalDemand::STATUS_AWAITING,
    ]);

    $this->actingAs($rh)->post(route('demands.start', $demand))->assertRedirect();
    $this->actingAs($rh)
        ->post(route('demands.review', $demand))
        ->assertSessionHasErrors();
    $this->actingAs($rh)
        ->post(route('demands.apply', $demand), ['payload' => ['pix_key' => 'nova-chave']])
        ->assertRedirect();

    expect($person->fresh()->pix_key)->toBe('nova-chave');
    expect($demand->fresh()->payload['applied'] ?? false)->toBeTrue();
});

it('does not let RH open an operational demand from the agenda', function () {
    $rh = demandActor('rh');

    $this->actingAs($rh)
        ->post(route('agenda.store'), [
            'title' => 'Demanda do RH',
            'assignee_id' => $rh->id,
            'type' => 'operational_demand',
            'request_text' => 'não deve',
            'category' => 'outros',
        ])
        ->assertSessionHasErrors('type');
});
