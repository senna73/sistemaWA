<?php

use App\Models\Collaborator;
use App\Models\CollaboratorWallet;
use App\Models\DailyRate;
use App\Models\Section;
use App\Models\User;
use App\Support\AccessControl;

function portalCollaboratorUser(): User
{
    AccessControl::seed();
    $collaborator = Collaborator::factory()->create();
    $user = User::factory()->create(['role' => 'collaborator', 'collaborator_id' => $collaborator->id]);
    AccessControl::applyToUser($user, 'collaborator');
    $user->collaborator_id = $collaborator->id;
    $user->save();

    return $user->fresh();
}

function portalSuperAdmin(): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => 'super_admin']);
    AccessControl::applyToUser($user, 'super_admin');

    return $user->fresh();
}

it('lets a collaborator see their own wallet balance', function () {
    $user = portalCollaboratorUser();
    CollaboratorWallet::query()->create([
        'collaborator_id' => $user->collaborator_id,
        'balance' => 150.75,
        'total_added' => 150.75,
        'total_spent' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('portal.earnings'))
        ->assertOk()
        ->assertSee('150,75')
        ->assertSee('Quanto você vai receber')
        ->assertDontSee('Buscar colaborador');

    $this->actingAs($user)
        ->get(route('portal.show'))
        ->assertOk()
        ->assertSee('150,75')
        ->assertSee('Meu cadastro')
        ->assertDontSee('Salvar');
});

it('blocks a collaborator from looking up another wallet', function () {
    $user = portalCollaboratorUser();
    $other = Collaborator::factory()->create(['name' => 'Outro Colaborador Saldo']);
    CollaboratorWallet::query()->create([
        'collaborator_id' => $other->id,
        'balance' => 999.11,
        'total_added' => 999.11,
        'total_spent' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('portal.earnings', ['collaborator_id' => $other->id, 'q' => $other->name]))
        ->assertOk()
        ->assertDontSee('Buscar colaborador')
        ->assertDontSee('999,11')
        ->assertDontSee('Outro Colaborador Saldo');

    $this->actingAs($user)
        ->get(route('admin.collaborator.earnings'))
        ->assertForbidden();
});

it('lets only a super admin search collaborator wallets', function () {
    $admin = portalSuperAdmin();
    $finance = User::factory()->create(['role' => 'leader']);
    AccessControl::applyToUser($finance, 'leader');
    $finance->givePermissionTo('Gerir pagamento de colaboradores e custos');

    $collaborator = Collaborator::factory()->create(['name' => 'Carteira Alvo']);
    CollaboratorWallet::query()->create([
        'collaborator_id' => $collaborator->id,
        'balance' => 320.40,
        'total_added' => 320.40,
        'total_spent' => 0,
    ]);

    $this->actingAs($finance)
        ->get(route('portal.earnings'))
        ->assertForbidden();

    $this->actingAs($finance)
        ->get(route('admin.collaborator.earnings'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('portal.earnings', ['q' => 'Carteira Alvo']))
        ->assertOk()
        ->assertSee('Buscar colaborador')
        ->assertSee('Carteira Alvo');

    $this->actingAs($admin)
        ->get(route('portal.earnings', ['q' => $collaborator->document]))
        ->assertOk()
        ->assertSee('320,40')
        ->assertSee('Vendo como')
        ->assertSee('Carteira Alvo');

    $this->actingAs($admin)
        ->get(route('admin.collaborator.earnings.single', $collaborator->id))
        ->assertRedirect(route('portal.earnings', ['collaborator_id' => $collaborator->id]));
});

it('lets a super admin open cadastro and daily rates as the collaborator', function () {
    $admin = portalSuperAdmin();
    $collaborator = Collaborator::factory()->create(['name' => 'Ana Portal']);
    $section = Section::query()->create(['name' => 'Padaria']);
    DailyRate::forceCreate([
        'collaborator_id' => $collaborator->id,
        'section_id' => $section->id,
        'start' => '2026-09-01 08:00:00',
        'end' => '2026-09-01 17:00:00',
        'pay_amount' => 88,
        'active' => true,
        'status' => 'criado',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Cadastro do colaborador')
        ->assertSee('Saldo do colaborador')
        ->assertSee('Diárias do colaborador');

    $this->actingAs($admin)
        ->get(route('portal.show'))
        ->assertOk()
        ->assertSee('Buscar colaborador')
        ->assertDontSee('Pedir demissão');

    $this->actingAs($admin)
        ->get(route('portal.show', ['q' => 'Ana Portal']))
        ->assertOk()
        ->assertSee('Vendo como')
        ->assertSee('Ana Portal');

    $this->actingAs($admin)
        ->get(route('portal.show', ['collaborator_id' => $collaborator->id]))
        ->assertOk()
        ->assertSee('Vendo como')
        ->assertSee('Ana Portal')
        ->assertDontSee('Pedir demissão');

    $this->actingAs($admin)
        ->get(route('portal.daily-rates', ['collaborator_id' => $collaborator->id]))
        ->assertOk()
        ->assertSee('Ana Portal')
        ->assertSee('88,00')
        ->assertSee('A receber');
});

it('shows the collaborator cadastro as read only', function () {
    $user = portalCollaboratorUser();

    $this->actingAs($user)
        ->put(route('portal.update'), [
            'name' => 'Nome Alterado',
            'pix_key' => 'chave-nova',
        ])
        ->assertForbidden();

    expect($user->collaborator->fresh()->name)->not->toBe('Nome Alterado');
});

it('lists the collaborator daily rates as view only', function () {
    $user = portalCollaboratorUser();
    $section = Section::query()->create(['name' => 'Caixa']);
    DailyRate::forceCreate([
        'collaborator_id' => $user->collaborator_id,
        'section_id' => $section->id,
        'start' => '2026-09-01 08:00:00',
        'end' => '2026-09-01 17:00:00',
        'pay_amount' => 120,
        'active' => true,
        'status' => 'criado',
    ]);
    DailyRate::forceCreate([
        'collaborator_id' => $user->collaborator_id,
        'section_id' => $section->id,
        'start' => '2026-09-10 08:00:00',
        'end' => '2026-09-10 17:00:00',
        'pay_amount' => 130,
        'active' => true,
        'status' => 'processado',
    ]);

    $this->actingAs($user)
        ->get(route('portal.daily-rates'))
        ->assertOk()
        ->assertSee('A receber')
        ->assertSee('Recebida')
        ->assertSee('120,00')
        ->assertSee('130,00')
        ->assertDontSee('Salvar')
        ->assertDontSee('Editar');
});
