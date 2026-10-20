<?php

use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('the modal emits the requested event only after confirmation', function () {
    Livewire::test('confirm-modal')
        ->dispatch('confirm-action', heading: 'Eliminar', message: 'Confirmar a remoção.', confirmEvent: 'family-force-delete-confirmed', params: ['id' => 7], confirmLabel: 'Eliminar')
        ->assertSet('params', ['id' => 7])
        ->assertNotDispatched('family-force-delete-confirmed')
        ->call('confirm')->assertDispatched('family-force-delete-confirmed', id: 7)
        ->assertSet('confirmEvent', '')->assertSet('params', []);
});

test('confirmation parameters cannot be changed from the browser', function (string $property, mixed $value) {
    expect(fn () => Livewire::test('confirm-modal')->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    ['confirmEvent', 'another-event'],
    ['params', ['id' => 999]],
]);

test('confirming an unopened modal is harmless', function () {
    Livewire::test('confirm-modal')->call('confirm')->assertHasNoErrors();
});
