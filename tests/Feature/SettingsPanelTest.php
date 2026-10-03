<?php

use App\Models\ConfigTable;
use App\Models\User;
use App\Support\AccessControl;

it('lets only a super admin open and save portal settings', function () {
    AccessControl::seed();

    $admin = User::factory()->create(['role' => 'super_admin']);
    AccessControl::applyToUser($admin, 'super_admin');

    $collaborator = User::factory()->create(['role' => 'collaborator']);
    AccessControl::applyToUser($collaborator, 'collaborator');

    $this->actingAs($collaborator)
        ->get(route('settings.index'))
        ->assertForbidden();

    $this->actingAs($admin->fresh())
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Liberar')
        ->assertSee('Meu saldo')
        ->assertSee('Diárias');

    $this->actingAs($admin)
        ->put(route('settings.update'), [
            'collaborator_earnings_enabled' => '1',
            'collaborator_daily_rates_enabled' => '0',
        ])
        ->assertRedirect();

    expect(ConfigTable::enabled(ConfigTable::PORTAL_EARNINGS))->toBeTrue();
    expect(ConfigTable::enabled(ConfigTable::PORTAL_DAILY_RATES))->toBeFalse();
});
