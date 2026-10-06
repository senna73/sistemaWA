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

it('keeps collaborator wallet and daily rates locked until cadastral release', function () {
    $user = portalCollaboratorUser();
    CollaboratorWallet::query()->create([
        'collaborator_id' => $user->collaborator_id,
        'balance' => 150.75,
        'total_added' => 150.75,
        'total_spent' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('portal.earnings'))
        ->assertRedirect(route('portal.show'));

    $this->actingAs($user)
        ->get(route('portal.show'))
        ->assertOk()
        ->assertSee('Meu cadastro')
        ->assertDontSee('150,75');
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
        ->assertRedirect(route('portal.show'));

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
        ->get(route('portal.daily-rates', [
            'collaborator_id' => $collaborator->id,
            'month' => '2026-09',
            'quinzena' => 1,
        ]))
        ->assertOk()
        ->assertSee('Ana Portal')
        ->assertSee('01/09/2026')
        ->assertSee('A receber')
        ->assertDontSee('88,00');
});

it('lets a collaborator request a pix change as an activity', function () {
    AccessControl::seed();
    $rh = User::factory()->create(['role' => 'rh']);
    AccessControl::applyToUser($rh, 'rh');
    $user = portalCollaboratorUser();

    $this->actingAs($user)
        ->put(route('portal.update'), [
            'pix_key' => 'chave-nova',
        ])
        ->assertRedirect();

    expect($user->collaborator->fresh()->pix_key)->not->toBe('chave-nova');
    expect($user->collaborator->fresh()->name)->not->toBe('Nome Alterado');
    $this->assertDatabaseHas('operational_demands', [
        'collaborator_id' => $user->collaborator_id,
        'category' => 'troca_pix',
    ]);
});

it('redirects collaborator daily rates until cadastral release', function () {
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
        ->assertRedirect(route('portal.show'));
});

it('lets a collaborator see wallet and daily rates when the super admin releases them', function () {
    \App\Models\ConfigTable::putBool(\App\Models\ConfigTable::PORTAL_EARNINGS, true);
    \App\Models\ConfigTable::putBool(\App\Models\ConfigTable::PORTAL_DAILY_RATES, true);

    $user = portalCollaboratorUser();
    CollaboratorWallet::query()->create([
        'collaborator_id' => $user->collaborator_id,
        'balance' => 150.75,
        'total_added' => 150.75,
        'total_spent' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Meu saldo')
        ->assertSee('Diárias');

    $this->actingAs($user)
        ->get(route('portal.earnings'))
        ->assertOk()
        ->assertSee('150,75')
        ->assertSee('Quanto você vai receber');
});

it('hides daily rate values and lists by quinzena for the collaborator', function () {
    \App\Models\ConfigTable::putBool(\App\Models\ConfigTable::PORTAL_DAILY_RATES, true);

    $user = portalCollaboratorUser();
    $section = Section::query()->create(['name' => 'Caixa']);
    $company = \App\Models\Company::query()->create(['name' => 'Loja Centro', 'coordinator_value' => 0]);

    foreach ([
        ['start' => '2026-10-01 08:00:00', 'pay' => 111],
        ['start' => '2026-10-15 08:00:00', 'pay' => 222],
        ['start' => '2026-10-16 08:00:00', 'pay' => 333],
        ['start' => '2026-10-31 08:00:00', 'pay' => 444],
        ['start' => '2026-02-28 08:00:00', 'pay' => 555],
    ] as $row) {
        DailyRate::forceCreate([
            'collaborator_id' => $user->collaborator_id,
            'section_id' => $section->id,
            'company_id' => $company->id,
            'start' => $row['start'],
            'end' => $row['start'],
            'pay_amount' => $row['pay'],
            'active' => true,
            'status' => 'criado',
        ]);
    }

    $this->actingAs($user)
        ->get(route('portal.daily-rates', ['month' => '2026-10', 'quinzena' => 1]))
        ->assertOk()
        ->assertSee('1ª (1 a 15)')
        ->assertSee('01/10/2026')
        ->assertSee('15/10/2026')
        ->assertSee('Loja Centro')
        ->assertDontSee('16/10/2026')
        ->assertDontSee('31/10/2026')
        ->assertDontSee('111,00')
        ->assertDontSee('222,00');

    $this->actingAs($user)
        ->get(route('portal.daily-rates', ['month' => '2026-10', 'quinzena' => 2]))
        ->assertOk()
        ->assertSee('2ª (16 a 31)')
        ->assertSee('16/10/2026')
        ->assertSee('31/10/2026')
        ->assertDontSee('01/10/2026')
        ->assertDontSee('15/10/2026')
        ->assertDontSee('444,00');

    $this->actingAs($user)
        ->get(route('portal.daily-rates', ['month' => '2026-02', 'quinzena' => 2]))
        ->assertOk()
        ->assertSee('2ª (16 a 28)')
        ->assertSee('28/02/2026')
        ->assertDontSee('555,00');
});

it('can release earnings without releasing daily rates', function () {
    \App\Models\ConfigTable::putBool(\App\Models\ConfigTable::PORTAL_EARNINGS, true);
    \App\Models\ConfigTable::putBool(\App\Models\ConfigTable::PORTAL_DAILY_RATES, false);

    $user = portalCollaboratorUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Meu saldo')
        ->assertDontSee('Diárias');

    $this->actingAs($user)
        ->get(route('portal.daily-rates'))
        ->assertRedirect(route('portal.show'));
});
