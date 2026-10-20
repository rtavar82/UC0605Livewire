<?php

use App\Models\Family;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
});

test('status filtering combines correctly with name and description search', function () {
    Family::factory()->create(['name' => 'Ativaceae', 'description' => 'Tema comum']);
    $removed = Family::factory()->create(['name' => 'Removidaceae', 'description' => 'Tema comum']);
    $removed->delete();

    $component = Livewire::test('pages::admin.families')->set('search', 'Tema comum');
    $component->set('status', 'active');
    expect($component->get('families')->pluck('name')->all())->toBe(['Ativaceae']);
    $component->set('status', 'trashed');
    expect($component->get('families')->pluck('name')->all())->toBe(['Removidaceae']);
    $component->set('status', 'all');
    expect($component->get('families')->total())->toBe(2);
    $component->set('status', 'unexpected');
    expect($component->get('families')->total())->toBe(2);
});

test('changing the status filter returns to the first page', function () {
    Family::factory()->count(16)->create();
    $component = Livewire::test('pages::admin.families')->call('setPage', 2)->set('status', 'trashed');
    expect($component->get('families')->currentPage())->toBe(1);
});

test('family details can be opened by slug from the address', function () {
    $family = Family::factory()->create(['slug' => 'araceae', 'description' => 'Descrição completa']);
    Livewire::withQueryParams(['family' => $family->slug])->test('pages::admin.families')
        ->assertSet('showing', 'araceae')->assertSee('Descrição completa');
});

test('an unknown family slug is cleared without failing the page', function () {
    Livewire::withQueryParams(['family' => 'nao-existe'])->test('pages::admin.families')
        ->assertSet('showing', '')->assertOk();
});

test('closing the detail clears the selected family', function () {
    $family = Family::factory()->create();
    Livewire::test('pages::admin.families')->call('show', $family->id)
        ->assertSet('showing', $family->slug)->call('closeShow')->assertSet('showing', '');
});

test('the detail includes removed families and their removal date', function () {
    $family = Family::factory()->create();
    $family->delete();
    Livewire::test('pages::admin.families')->call('show', $family->id)
        ->assertSee('Removida em')->assertSee($family->deleted_at->format('d/m/Y H:i'));
});

test('editing or removing a family closes its details', function (string $action) {
    $family = Family::factory()->create();
    Livewire::test('pages::admin.families')->call('show', $family->id)
        ->call($action, $family->id)->assertSet('showing', '');
})->with(['edit', 'delete']);

test('restoring a family keeps its detail open with the updated status', function () {
    $family = Family::factory()->create();
    $family->delete();
    $component = Livewire::test('pages::admin.families')->call('show', $family->id)->call('restore', $family->id)
        ->assertSet('showing', $family->slug);
    expect($component->get('shownFamily')->trashed())->toBeFalse();
});

test('requesting confirmation does not delete a family', function () {
    $family = Family::factory()->create();
    $family->delete();

    Livewire::test('pages::admin.families')->call('confirmForceDelete', $family->id)
        ->assertDispatched('confirm-action', confirmEvent: 'family-force-delete-confirmed', params: ['id' => $family->id]);
    $this->assertDatabaseHas('families', ['id' => $family->id]);
});

test('the confirmed event deletes only removed families and closes the detail', function () {
    $family = Family::factory()->create();
    $family->delete();

    Livewire::test('pages::admin.families')->call('show', $family->id)
        ->dispatch('family-force-delete-confirmed', id: $family->id)->assertSet('showing', '');
    $this->assertDatabaseMissing('families', ['id' => $family->id]);
});

test('active families cannot be requested or deleted through a confirmation event', function (string $method) {
    $family = Family::factory()->create();
    $component = Livewire::test('pages::admin.families');
    if ($method === 'request') {
        $component->call('confirmForceDelete', $family->id)->assertNotFound();
    } else {
        $component->dispatch('family-force-delete-confirmed', id: $family->id)->assertNotFound();
    }
    $this->assertDatabaseHas('families', ['id' => $family->id]);
})->with(['request', 'event']);

test('ordinary users cannot access a shared family detail address', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user)->get(route('admin.families.index', ['family' => 'araceae']))->assertForbidden();
});
