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
    foreach (array_keys(RhActivitySettings::catalog()) as $key) {
        $payload['rh_activity_'.$key] = '1';
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
        ->assertSee('Solicitar desligamento');

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
