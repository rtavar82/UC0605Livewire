<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('guests must log in before opening either dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('each role can open only its own dashboard', function (string $role, string $allowed, string $forbidden) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get(route($allowed))->assertOk()->assertSee($user->name);
    $this->get(route($forbidden))->assertForbidden();
})->with([
    ['user', 'dashboard', 'admin.dashboard'],
    ['admin', 'admin.dashboard', 'dashboard'],
]);

test('login sends each role to its dashboard', function (string $role, string $dashboard) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route($dashboard));
    $this->get(route('login'))->assertRedirect(route($dashboard));
})->with([
    ['user', 'dashboard'],
    ['admin', 'admin.dashboard'],
]);

test('login preserves the intended settings page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('profile.edit'));
});

test('unverified users must verify their email before opening a dashboard', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole('user');

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('quick login is unavailable outside the local environment', function () {
    $this->post(route('dev.login', 'admin'))->assertNotFound();
    $this->assertGuest();
});

test('local quick login accepts only the predefined accounts', function () {
    $this->app['env'] = 'local';
    $admin = User::factory()->create(['email' => 'admin@florahub.test']);
    $admin->assignRole('admin');

    $this->post(route('dev.login', 'unknown'))->assertNotFound();
    $this->assertGuest();
    $this->withSession(['_token' => 'test-token'])->post(route('dev.login', 'admin'), ['_token' => 'test-token'])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('quick login returns not found when its development account is missing', function () {
    $this->app['env'] = 'local';
    $this->withSession(['_token' => 'test-token'])->post(route('dev.login', 'user'), ['_token' => 'test-token'])->assertNotFound();
    $this->assertGuest();
});

test('password confirmation screen can be rendered in Portuguese', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->get(route('password.confirm'))->assertOk()->assertSee('Confirmar palavra-passe');
});
