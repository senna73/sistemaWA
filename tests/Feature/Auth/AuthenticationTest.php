<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\Collaborator;
use App\Models\User;
use App\Support\AccessControl;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using email after identify step', function () {
    $user = User::factory()->create();

    $this->post('/login/identify', [
        'identifier' => $user->email,
    ])->assertRedirect(route('login'));

    $response = $this->post('/login', [
        'identifier' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('login does not force authentication as user id 1', function () {
    User::factory()->create();
    $other = User::factory()->create();

    $this->post('/login/identify', ['identifier' => $other->email]);
    $this->post('/login', [
        'identifier' => $other->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($other);
});

test('unknown cpf shows a generic error', function () {
    $this->from('/login')->post('/login/identify', [
        'identifier' => '529.982.247-25',
    ])->assertSessionHasErrors('identifier');

    $this->assertGuest();
    expect(session('errors')->first('identifier'))->toBe(AuthenticatedSessionController::GENERIC_LOGIN_ERROR);
});

test('collaborator can complete first access with cpf then login later', function () {
    AccessControl::seed();

    $collaborator = Collaborator::factory()->create([
        'document' => '52998224725',
    ]);

    $this->post('/login/identify', [
        'identifier' => '529.982.247-25',
    ])->assertRedirect(route('login.first-access'));

    $this->post('/login/first-access', [
        'email' => 'colab@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'colab@example.com',
        'collaborator_id' => $collaborator->id,
        'role' => 'employee',
    ]);

    $this->get('/logout');
    $this->assertGuest();

    $this->post('/login/identify', ['identifier' => '52998224725']);
    $this->post('/login', [
        'identifier' => '52998224725',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login/identify', ['identifier' => $user->email]);
    $this->post('/login', [
        'identifier' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
