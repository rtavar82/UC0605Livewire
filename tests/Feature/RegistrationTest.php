<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

test('registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk()->assertSee('Crie a sua conta');
});

test('registration creates an unverified user with the user role', function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'nova@example.test')->firstOrFail();
    expect($user->hasRole('user'))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('registration rolls back the user when assigning the role fails', function () {
    expect(fn () => app(CreateNewUser::class)->create([
        'name' => 'Nova Pessoa',
        'email' => 'rollback@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]))->toThrow(RoleDoesNotExist::class);

    $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
});
