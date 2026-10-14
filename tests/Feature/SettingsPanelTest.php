<?php

use App\Models\ConfigTable;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\RhActivitySettings;

function settingsAdmin(): User
{
    AccessControl::seed();
    $admin = User::factory()->create(['role' => 'super_admin']);
    AccessControl::applyToUser($admin, 'super_admin');

    return $admin->fresh();
}

function rhActivityPayload(array $overrides = []): array
{
    $payload = [];
    foreach (RhActivitySettings::catalog() as $key => $activity) {
        $payload['rh_activity_'.$key] = '1';
        $payload['rh_activity_'.$key.'_audiences'] = $activity['audiences'];
        $payload['rh_activity_'.$key.'_sectors'] = $activity['sectors'];
    }

    return array_merge($payload, $overrides);
}

it('lets only a super admin open and save portal settings', function () {
    $admin = settingsAdmin();

    $collaborator = User::factory()->create(['role' => 'collaborator']);
    AccessControl::applyToUser($collaborator, 'collaborator');

    $this->actingAs($collaborator)
        ->get(route('settings.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Liberar')
        ->assertSee('Meu saldo')
        ->assertSee('Diárias')
        ->assertSee('Portal do colaborador')
        ->assertSee('Atividades do RH')
        ->assertSee('Demandas a atender')
        ->assertSee('Gestão RH Demissional')
        ->assertSee('Processo de contratação')
        ->assertSee('Solicitar desligamento')
        ->assertSee('Papel')
        ->assertSee('Setor da agenda')
        ->assertSee('Conferência Cliomed')
        ->assertSee('Assistente de Departamento Pessoal');

    $this->actingAs($admin)
        ->put(route('settings.update'), rhActivityPayload([
            'collaborator_earnings_enabled' => '1',
            'collaborator_daily_rates_enabled' => '0',
        ]))
        ->assertRedirect();

    expect(ConfigTable::enabled(ConfigTable::PORTAL_EARNINGS))->toBeTrue();
    expect(ConfigTable::enabled(ConfigTable::PORTAL_DAILY_RATES))->toBeFalse();
    expect(RhActivitySettings::enabled(RhActivitySettings::DEMANDS))->toBeTrue();
    expect(RhActivitySettings::enabled(RhActivitySettings::OFFBOARDING))->toBeTrue();
});

it('starts rh activity cards enabled and lets the admin hide one by one', function () {
    $admin = settingsAdmin();

    expect(RhActivitySettings::enabled(RhActivitySettings::DEMANDS))->toBeTrue()
        ->and(RhActivitySettings::enabled(RhActivitySettings::OFFBOARDING))->toBeTrue()
        ->and(RhActivitySettings::enabled(RhActivitySettings::OFFBOARDING_REQUEST))->toBeTrue();

    $this->actingAs($admin)
        ->put(route('settings.update'), rhActivityPayload([
            'collaborator_earnings_enabled' => '0',
            'collaborator_daily_rates_enabled' => '0',
            'rh_activity_demands' => '0',
            'rh_activity_offboarding' => '0',
        ]))
        ->assertRedirect();

    expect(RhActivitySettings::enabled(RhActivitySettings::DEMANDS))->toBeFalse();
    expect(RhActivitySettings::enabled(RhActivitySettings::OFFBOARDING))->toBeFalse();
    expect(RhActivitySettings::enabled(RhActivitySettings::CLIOMED))->toBeTrue();
    expect(RhActivitySettings::enabled(RhActivitySettings::OFFBOARDING_REQUEST))->toBeTrue();
});

it('lets the director restrict an activity to a sector', function () {
    $admin = settingsAdmin();
    $rh = User::factory()->create(['role' => 'rh']);
    AccessControl::applyToUser($rh, 'rh');
    $accounting = User::factory()->create(['role' => 'accounting']);
    AccessControl::applyToUser($accounting, 'accounting');

    $this->actingAs($admin)
        ->put(route('settings.update'), rhActivityPayload([
            'rh_activity_cliomed_audiences' => ['rh'],
            'rh_activity_accounting_audiences' => ['accounting'],
        ]))
        ->assertRedirect();

    expect(RhActivitySettings::audiences(RhActivitySettings::CLIOMED))->toBe(['rh'])
        ->and(RhActivitySettings::visible(RhActivitySettings::CLIOMED, $rh->fresh()))->toBeTrue()
        ->and(RhActivitySettings::visible(RhActivitySettings::CLIOMED, $accounting->fresh()))->toBeFalse()
        ->and(RhActivitySettings::visible(RhActivitySettings::ACCOUNTING, $rh->fresh()))->toBeFalse()
        ->and(RhActivitySettings::visible(RhActivitySettings::ACCOUNTING, $accounting->fresh()))->toBeTrue()
        ->and(RhActivitySettings::visible(RhActivitySettings::CLIOMED, $admin))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('work.home'))
        ->assertOk()
        ->assertSee('Conferência Cliomed')
        ->assertSee('Editar quem vê cada área')
        ->assertSee('DP')
        ->assertSee('Recrutamento');

    $this->actingAs($accounting->fresh())
        ->get(route('work.cliomed'))
        ->assertForbidden();

    $this->actingAs($rh->fresh())
        ->get(route('work.accounting'))
        ->assertForbidden();
});

it('restricts a screen to the agenda sector of the user', function () {
    $admin = settingsAdmin();
    $dp = User::factory()->create(['role' => 'rh', 'work_function' => 'dp_assistant']);
    AccessControl::applyToUser($dp, 'rh');
    $recruitment = User::factory()->create(['role' => 'rh', 'work_function' => 'recruitment']);
    AccessControl::applyToUser($recruitment, 'rh');
    $noSector = User::factory()->create(['role' => 'rh', 'work_function' => null]);
    AccessControl::applyToUser($noSector, 'rh');

    $this->actingAs($admin)
        ->put(route('settings.update'), rhActivityPayload([
            'rh_activity_cliomed_audiences' => ['rh'],
            'rh_activity_cliomed_sectors' => ['recruitment'],
        ]))
        ->assertRedirect();

    expect(RhActivitySettings::sectors(RhActivitySettings::CLIOMED))->toBe(['recruitment'])
        ->and(RhActivitySettings::visible(RhActivitySettings::CLIOMED, $recruitment->fresh()))->toBeTrue()
        ->and(RhActivitySettings::visible(RhActivitySettings::CLIOMED, $dp->fresh()))->toBeFalse()
        ->and(RhActivitySettings::visible(RhActivitySettings::CLIOMED, $noSector->fresh()))->toBeFalse();

    $this->actingAs($dp->fresh())->get(route('work.cliomed'))->assertForbidden();
    $this->actingAs($recruitment->fresh())->get(route('work.cliomed'))->assertOk();
});
