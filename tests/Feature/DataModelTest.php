<?php

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

test('relaciona un usuario con sus contactos', function () {
    // Preparar
    $user = User::factory()->create();

    // Actuar
    Contact::factory(3)->for($user)->create();

    // Comprobar
    expect($user->contacts)->toHaveCount(3)
        ->and($user->contacts->first()->user->is($user))->toBeTrue();
});

test('hace borrado lógico de los contactos', function () {
    // Preparar
    $contact = Contact::factory()->create();

    // Actuar
    $contact->delete();

    // Comprobar
    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);

    expect(Contact::count())->toBe(0)
        ->and(Contact::withTrashed()->count())->toBe(1);
});

test('deja nombre y dirección visibles por defecto', function () {
    // Actuar
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    // Comprobar: en la base y en el objeto recién creado
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name_visible' => true,
        'address_visible' => true,
    ]);
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name_visible' => true,
        'address_visible' => true,
    ]);

    expect($contact->name_visible)->toBeTrue()
        ->and($contact->address_visible)->toBeTrue();
});

test('no permite dos usuarios con el mismo correo ni el mismo teléfono', function () {
    // Preparar
    User::factory()->create([
        'email' => 'repetido@contacts.test',
        'phone_number' => '3001234567',
    ]);

    // Actuar
    $correoRepetido = fn () => User::factory()->create(['email' => 'repetido@contacts.test']);
    $telefonoRepetido = fn () => User::factory()->create(['phone_number' => '3001234567']);

    // Comprobar
    expect($correoRepetido)->toThrow(UniqueConstraintViolationException::class);
    expect($telefonoRepetido)->toThrow(UniqueConstraintViolationException::class);
    $this->assertDatabaseCount('users', 1);
});

test('borra los contactos de un usuario cuando se elimina el usuario', function () {
    // Preparar
    $user = User::factory()->create();
    Contact::factory(2)->for($user)->create();

    // Actuar
    $user->delete();

    // Comprobar
    $this->assertDatabaseCount('contacts', 0);
});

test('siembra dos usuarios con sus contactos', function () {
    // Actuar
    $this->seed(DatabaseSeeder::class);

    // Comprobar
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('contacts', 8);
});