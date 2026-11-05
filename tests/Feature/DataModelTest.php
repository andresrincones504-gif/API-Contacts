<?php

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

it('relaciona un usuario con sus contactos', function () {
    $user = User::factory()->create();
    Contact::factory(3)->for($user)->create();

    expect($user->contacts)->toHaveCount(3)
        ->and($user->contacts->first()->user->is($user))->toBeTrue();
});

it('hace borrado lógico de los contactos', function () {
    $contact = Contact::factory()->create();

    $contact->delete();

    expect(Contact::count())->toBe(0)
        ->and(Contact::find($contact->id))->toBeNull()
        ->and(Contact::withTrashed()->count())->toBe(1);
});

it('deja nombre y dirección visibles por defecto', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    expect($user->fresh()->name_visible)->toBeTrue()
        ->and($user->fresh()->address_visible)->toBeTrue()
        ->and($contact->fresh()->name_visible)->toBeTrue()
        ->and($contact->fresh()->address_visible)->toBeTrue();
});

it('no permite dos usuarios con el mismo correo', function () {
    User::factory()->create(['email' => 'repetido@contacts.test']);
    User::factory()->create(['email' => 'repetido@contacts.test']);
})->throws(UniqueConstraintViolationException::class);

it('no permite dos usuarios con el mismo teléfono', function () {
    User::factory()->create(['phone_number' => '3001234567']);
    User::factory()->create(['phone_number' => '3001234567']);
})->throws(UniqueConstraintViolationException::class);

it('borra los contactos de un usuario cuando se elimina el usuario', function () {
    $user = User::factory()->create();
    Contact::factory(2)->for($user)->create();

    $user->delete();

    expect(Contact::withTrashed()->count())->toBe(0);
});

it('siembra dos usuarios con sus contactos', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(2)
        ->and(Contact::count())->toBe(8);
});
