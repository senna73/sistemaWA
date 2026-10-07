<?php

use App\Models\Collaborator;
use App\Models\ConfigTable;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\RhActivitySettings;
use App\Support\SidebarMenu;
use Illuminate\Http\Request;

function sidebarUser(string $role): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

function sidebarGroup(?User $user, string $id): ?array
{
    return collect(SidebarMenu::items($user, Request::create('/dashboard', 'GET')))
        ->firstWhere('id', $id);
}

it('hides admin sidebar links the coordinator cannot open', function () {
    $user = sidebarUser('coordinator');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('RH Controle')
        ->assertSee('Solicitar desligamento')
        ->assertSee('Uniformes')
        ->assertSee('data-sidebar-group="rh"', false)
        ->assertSee('bx-home-circle', false)
        ->assertSee('bx-briefcase', false)
        ->assertSee('bx-calendar-event', false)
        ->assertSee('bx-user-minus', false)
        ->assertSee('bx-closet', false)
        ->assertSee('bx-message-square-dots', false)
        ->assertDontSee('data-sidebar-group="admin"', false)
        ->assertDontSee('data-sidebar-group="finance"', false)
        ->assertDontSee('data-sidebar-group="operation"', false)
        ->assertDontSee('Usuários')
        ->assertDontSee('Estabelecimentos')
        ->assertDontSee('Analytics Financeiro')
        ->assertDontSee('Processamento')
        ->assertDontSee('Centro de Pagamentos')
        ->assertDontSee('Acompanhamento RH');
});

it('hides portal requests from a coordinator even with cadastro vinculado', function () {
    $user = sidebarUser('coordinator');
    $user->collaborator_id = Collaborator::factory()->create()->id;
    $user->givePermissionTo(AccessControl::PERMISSION_PORTAL);
    $user->save();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Solicitações')
        ->assertDontSee('Solicitações do colaborador');
});

it('shows only portal links for a collaborator', function () {
    $user = sidebarUser('collaborator');
    $user->collaborator_id = Collaborator::factory()->create()->id;
    $user->save();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Meu cadastro')
        ->assertSee('Solicitações')
        ->assertSee('data-sidebar-group="portal"', false)
        ->assertDontSee('data-sidebar-group="rh"', false)
        ->assertDontSee('Meu saldo')
        ->assertDontSee('Diárias')
        ->assertDontSee('RH Controle')
        ->assertDontSee('Usuários')
        ->assertDontSee('Administração')
        ->assertDontSee('Financeiro')
        ->assertDontSee('Operação');
});

it('shows the collaborator list to RH without the direction inbox', function () {
    $user = sidebarUser('rh');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Colaboradores')
        ->assertSee('RH Controle')
        ->assertSee('Demandas a atender')
        ->assertSee('data-sidebar-group="rh"', false)
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
        ->assertSee('Configurações')
        ->assertSee('data-sidebar-group="rh"', false)
        ->assertDontSee('Usuários')
        ->assertDontSee('data-sidebar-group="finance"', false);
});

it('collapses a group to a direct link when only one item is visible', function () {
    $user = sidebarUser('accounting');
    $labels = collect(SidebarMenu::items($user, Request::create('/dashboard', 'GET')))->pluck('label');

    expect(sidebarGroup($user, 'rh'))->toBeNull()
        ->and(sidebarGroup($user, 'admin'))->toBeNull()
        ->and(sidebarGroup($user, 'finance'))->toBeNull()
        ->and(sidebarGroup($user, 'operation'))->toBeNull()
        ->and($labels)->toContain('RH Controle')
        ->and($labels)->toContain('Uniformes')
        ->and($labels)->not->toContain('RH')
        ->and($labels)->not->toContain('Administração')
        ->and($labels)->not->toContain('Financeiro')
        ->and($labels)->not->toContain('Operação');
});

it('keeps money, operation and system screens in separate groups', function () {
    $user = sidebarUser('rh');
    $user->givePermissionTo([
        'Lista de usuários',
        'Lista de estabelecimentos',
        'Lista de diárias',
        'Visualizar e inserir informações financeiras nas diárias',
        'Processar boletos e confirmar recebimento',
        'Gerir pagamento de colaboradores e custos',
        'Gestão dos centros de custo',
        'Visualizar livro razão',
        'Acesso aos dados de diárias',
    ]);

    $actor = $user->fresh();
    $operation = sidebarGroup($actor, 'operation');
    $finance = sidebarGroup($actor, 'finance');
    $admin = sidebarGroup($actor, 'admin');

    expect(collect($operation['children'])->pluck('label')->all())->toEqual([
        'Estabelecimentos',
        'Diárias',
        'Uniformes',
    ])
        ->and(collect($finance['children'])->pluck('label')->all())->toEqual([
            'Centro de Pagamentos',
            'Processamento',
            'Centro de Custo',
            'Gestão de Centros',
            'Analytics Financeiro',
            'Análise de Dados',
            'Capital Empresarial',
        ])
        ->and(collect($admin['children'])->pluck('label')->all())->toEqual([
            'Usuários',
            'Usuários apagados',
        ])
        ->and(collect($operation['children'])->pluck('icon')->all())->toEqual([
            'bx-store',
            'bx-calendar-event',
            'bx-closet',
        ])
        ->and(collect($finance['children'])->pluck('icon')->all())->toEqual([
            'bx-credit-card',
            'bx-cog',
            'bx-list-check',
            'bx-buildings',
            'bx-pie-chart-alt-2',
            'bx-bar-chart-alt-2',
            'bx-book-content',
        ])
        ->and(collect($admin['children'])->pluck('icon')->all())->toEqual([
            'bx-user',
            'bx-user-x',
        ]);

    $this->actingAs($actor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('bx-store', false)
        ->assertSee('bx-credit-card', false)
        ->assertSee('bx-pie-chart-alt-2', false)
        ->assertSee('bx-user', false)
        ->assertSee('bx-group', false);
});

it('expands RH when the user can open more than one function', function () {
    $user = sidebarUser('coordinator');
    $rh = sidebarGroup($user, 'rh');

    expect($rh)->not->toBeNull()
        ->and($rh['type'])->toBe('group')
        ->and(collect($rh['children'])->pluck('label')->all())->toEqual([
            'RH Controle',
            'Agenda',
            'Solicitar desligamento',
        ]);
});

it('hides disabled rh activities from the sidebar', function () {
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::OFFBOARDING_REQUEST), false);
    ConfigTable::putBool(RhActivitySettings::flag(RhActivitySettings::DEMANDS), false);

    $coordinator = sidebarUser('coordinator');
    $rh = sidebarUser('rh');

    expect(collect(sidebarGroup($coordinator, 'rh')['children'])->pluck('label')->all())->toEqual([
        'RH Controle',
        'Agenda',
    ]);

    $this->actingAs($rh)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('RH Controle')
        ->assertDontSee('Demandas a atender');
});
