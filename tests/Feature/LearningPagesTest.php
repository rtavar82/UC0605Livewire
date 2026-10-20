<?php

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->app['env'] = 'local';
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();
});

test('all learning pages render in the local environment', function () {
    $this->app['env'] = 'local';
    foreach (['tailwind', 'components', 'livewire', 'flux'] as $page) {
        $this->get('/dev/'.$page)->assertOk();
    }
});

test('the learning counter never becomes negative and can be reset', function () {
    Livewire::test('pages::dev.livewire')->call('decrement')->assertSet('count', 0)
        ->call('increment')->assertSet('count', 1)->call('resetCount')->assertSet('count', 0);
});

test('livewire search is case insensitive and the form validates input', function () {
    Livewire::test('pages::dev.livewire')->set('search', 'ROSA')->assertSee('Rosa canina')->assertDontSee('Olea europaea')
        ->call('save')->assertHasErrors(['suggestion', 'email'])
        ->set('suggestion', 'Nova espécie')->set('email', 'pessoa@example.test')
        ->call('save')->assertHasNoErrors()->assertSet('saved', 'Nova espécie')->assertSet('suggestion', '')->assertSet('email', '');
});

test('the child like component publishes its new state', function () {
    Livewire::test('species-like', ['species' => 'Rosa canina'])->call('toggle')->assertSet('liked', true)
        ->assertDispatched('species-liked', species: 'Rosa canina', liked: true);
});

test('the flux example validates its form and resets saved fields', function () {
    Livewire::test('pages::dev.flux')->call('save')->assertHasErrors(['commonName', 'family', 'notes', 'agree'])
        ->set('commonName', 'Rosa-brava')->set('family', 'rosaceae')->set('notes', 'Notas completas da planta.')
        ->set('agree', true)->call('save')->assertHasNoErrors()
        ->assertSet('commonName', '')->assertSet('family', '')->assertSet('notes', '')->assertSet('agree', false);
});
