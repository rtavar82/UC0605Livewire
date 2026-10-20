<?php

use App\Models\Family;
use App\Models\User;
use Database\Seeders\FamilySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Middleware\RoleMiddleware;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
});

test('only administrators can open the families page', function () {
    $this->get(route('admin.families.index'))->assertOk()->assertSee('Famílias botânicas');

    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user)->get(route('admin.families.index'))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.families.index'))->assertRedirect(route('login'));
});

test('the role middleware is persistent on Livewire requests', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(RoleMiddleware::class);
});

test('search matches family names and descriptions including removed families', function () {
    $araceae = Family::factory()->create(['name' => 'Araceae', 'slug' => 'araceae', 'description' => 'Folhas tropicais']);
    $cactus = Family::factory()->create(['name' => 'Cactaceae', 'slug' => 'cactaceae', 'description' => 'Plantas suculentas']);
    $cactus->delete();

    Livewire::test('pages::admin.families')
        ->set('search', 'Araceae')->assertSee('Araceae')->assertDontSee('Cactaceae')
        ->set('search', 'suculentas')->assertSee('Cactaceae')->assertSee('Removida')->assertDontSee('Folhas tropicais')
        ->set('search', 'xyz')->assertSee('Nenhuma família encontrada.');
});

test('pagination contains fifteen families and resets after searching', function () {
    Family::factory()->count(20)->create();

    $component = Livewire::test('pages::admin.families');
    expect($component->get('families')->count())->toBe(15);
    $component->call('setPage', 2);
    expect($component->get('families')->currentPage())->toBe(2)
        ->and($component->get('families')->count())->toBe(5);

    $component->set('search', 'aceae');
    expect($component->get('families')->currentPage())->toBe(1);
});

test('creating a family generates a slug and stores an empty description as null', function () {
    Livewire::test('pages::admin.families')
        ->call('create')->set('name', 'Asteraceae')->assertSet('slug', 'asteraceae')
        ->call('save')->assertHasNoErrors()->assertSet('name', '')->assertSet('editingId', null);

    $this->assertDatabaseHas('families', ['name' => 'Asteraceae', 'slug' => 'asteraceae', 'description' => null, 'image' => null]);
});

test('a manually edited slug is preserved when changing the name', function () {
    Livewire::test('pages::admin.families')
        ->set('name', 'Asteraceae')->assertSet('slug', 'asteraceae')
        ->set('slug', 'compostas')->set('name', 'Asteraceae Juss.')
        ->assertSet('slug', 'compostas');
});

test('invalid family input cannot be saved', function (array $fields, array $errors) {
    $component = Livewire::test('pages::admin.families');
    foreach ($fields as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('save')->assertHasErrors($errors);
    $this->assertDatabaseCount('families', 0);
})->with([
    [['name' => '', 'slug' => ''], ['name' => 'required', 'slug' => 'required']],
    [['name' => 'Araceae', 'slug' => 'Ara ceae!'], ['slug' => 'regex']],
    [['name' => 'Araceae', 'slug' => 'araceae', 'description' => str_repeat('x', 2001)], ['description' => 'max']],
]);

test('removed families keep their names and slugs reserved', function () {
    $family = Family::factory()->create(['name' => 'Araceae', 'slug' => 'araceae']);
    $family->delete();

    Livewire::test('pages::admin.families')->set('name', 'Araceae')->set('slug', 'araceae')
        ->call('save')->assertHasErrors(['name' => 'unique', 'slug' => 'unique']);
    $this->assertDatabaseCount('families', 1);
});

test('editing a family permits keeping its own name and slug', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::admin.families')->call('edit', $family->id)
        ->assertSet('editingId', $family->id)->assertSet('name', $family->name)
        ->set('description', 'Descrição atualizada')->call('save')->assertHasNoErrors();

    expect($family->fresh()->description)->toBe('Descrição atualizada');
});

test('opening a new form clears prior fields and validation errors', function () {
    $family = Family::factory()->create();
    Livewire::test('pages::admin.families')
        ->call('edit', $family->id)->set('name', '')->call('save')->assertHasErrors('name')
        ->call('create')->assertSet('editingId', null)->assertSet('name', '')->assertSet('slug', '')->assertHasNoErrors();
});

test('a family can be removed restored and permanently deleted', function () {
    $family = Family::factory()->create();
    $component = Livewire::test('pages::admin.families')->call('delete', $family->id);
    $this->assertSoftDeleted('families', ['id' => $family->id]);

    $component->call('restore', $family->id);
    expect($family->fresh()->trashed())->toBeFalse();

    $component->call('delete', $family->id)->call('forceDelete', $family->id);
    $this->assertDatabaseMissing('families', ['id' => $family->id]);
});

test('active families cannot be restored or permanently deleted', function (string $action) {
    $family = Family::factory()->create();
    Livewire::test('pages::admin.families')->call($action, $family->id)->assertNotFound();
    $this->assertDatabaseHas('families', ['id' => $family->id, 'deleted_at' => null]);
})->with(['restore', 'forceDelete']);

test('removed families cannot be edited', function () {
    $family = Family::factory()->create();
    $family->delete();
    Livewire::test('pages::admin.families')->call('edit', $family->id)->assertNotFound();
});

test('the dashboard counts only active families', function () {
    Family::factory()->count(3)->create();
    $removed = Family::factory()->create();
    $removed->delete();

    $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('familiesCount', 3);
});

test('family seeding is idempotent and does not restore removed families', function () {
    $this->seed(FamilySeeder::class);
    $family = Family::where('slug', 'rosaceae')->firstOrFail();
    $family->delete();
    $this->seed(FamilySeeder::class);

    expect(Family::withTrashed()->count())->toBe(10)
        ->and(Family::count())->toBe(9)
        ->and($family->fresh()->trashed())->toBeTrue();
});

test('an administrator who loses the role cannot send further Livewire actions', function () {
    $family = Family::factory()->create();
    $page = $this->get(route('admin.families.index'))->assertOk();
    preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

    auth()->user()->syncRoles('user');

    $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['path' => '', 'method' => 'delete', 'params' => [$family->id]]],
        ]],
    ], ['X-Livewire' => 'true'])->assertForbidden();

    $this->assertDatabaseHas('families', ['id' => $family->id, 'deleted_at' => null]);
});
