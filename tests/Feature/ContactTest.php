<?php

use App\Models\Contact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function contactData(array $cambios = []): array
{
    return array_merge([
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
        'address' => 'Calle 10 # 5-20',
    ], $cambios);
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('exige sesión en todos los endpoints de contactos', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/contacts'],
    ['POST', '/api/v1/contacts'],
    ['GET', '/api/v1/contacts/1'],
    ['PUT', '/api/v1/contacts/1'],
    ['DELETE', '/api/v1/contacts/1'],
]);

it('lista solo los contactos del usuario autenticado', function () {
    Contact::factory(3)->for($this->user)->create();
    Contact::factory(2)->create();
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/contacts')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('crea un contacto asignado al usuario autenticado', function () {
    $otro = User::factory()->create();
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/contacts', contactData(['user_id' => $otro->id]))
        ->assertCreated()
        ->assertJsonPath('data.email', 'carlos@correo.test');

    $this->assertDatabaseHas('contacts', [
        'email' => 'carlos@correo.test',
        'user_id' => $this->user->id,
    ]);
});

it('valida los campos obligatorios al crear', function (string $campo) {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/contacts', contactData([$campo => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo]);
})->with(['name', 'email', 'phone_number']);

it('rechaza un teléfono con formato inválido', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/contacts', contactData(['phone_number' => 'abc123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});

it('rechaza un correo o teléfono que ya usa otro usuario o contacto', function (string $campo, string $valor, string $origen) {
    if ($origen === 'usuario') {
        User::factory()->create([$campo => $valor]);
    } else {
        Contact::factory()->create([$campo => $valor]);
    }
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/contacts', contactData([$campo => $valor]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo]);
})->with([
    ['email', 'repetido@correo.test', 'usuario'],
    ['email', 'repetido@correo.test', 'contacto'],
    ['phone_number', '3001112222', 'usuario'],
    ['phone_number', '3001112222', 'contacto'],
]);

it('permite reutilizar el correo y teléfono de un contacto eliminado', function () {
    Contact::factory()->create([
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ])->delete();
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/contacts', contactData())->assertCreated();
});

it('muestra el perfil de un contacto propio', function () {
    $contact = Contact::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.name', $contact->name)
        ->assertJsonPath('data.address', $contact->address);
});

it('oculta el nombre y la dirección en el perfil si están marcados como ocultos', function () {
    $contact = Contact::factory()->for($this->user)->create([
        'name_visible' => false,
        'address_visible' => false,
    ]);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.email', $contact->email);
});

it('actualiza un contacto propio', function () {
    $contact = Contact::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);

    $this->putJson("/api/v1/contacts/{$contact->id}", [
        'name' => 'Nombre Nuevo',
        'address_visible' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nombre Nuevo');

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'Nombre Nuevo',
        'address_visible' => false,
    ]);
});

it('permite guardar un contacto con su propio correo y teléfono', function () {
    $contact = Contact::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);

    $this->putJson("/api/v1/contacts/{$contact->id}", contactData([
        'email' => $contact->email,
        'phone_number' => $contact->phone_number,
    ]))->assertOk();
});

it('rechaza actualizar con un correo o teléfono de otro contacto', function (string $campo) {
    $contact = Contact::factory()->for($this->user)->create();
    $otro = Contact::factory()->create();
    Sanctum::actingAs($this->user);

    $this->putJson("/api/v1/contacts/{$contact->id}", [$campo => $otro->{$campo}])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo]);
})->with(['email', 'phone_number']);

it('elimina un contacto con borrado lógico', function () {
    $contact = Contact::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);

    $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertNoContent();

    $this->assertSoftDeleted($contact);
});

it('responde 404 para un contacto eliminado', function () {
    $contact = Contact::factory()->for($this->user)->create();
    $contact->delete();
    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/contacts/{$contact->id}")->assertNotFound();
});

it('responde 404 en JSON si el contacto no existe', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/contacts/99999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Recurso no encontrado.');
});

it('no deja ver, editar ni eliminar el contacto de otro usuario', function (string $method) {
    $ajeno = Contact::factory()->create();
    Sanctum::actingAs($this->user);

    $this->json($method, "/api/v1/contacts/{$ajeno->id}", contactData())
        ->assertForbidden();

    $this->assertNotSoftDeleted($ajeno);
})->with(['GET', 'PUT', 'DELETE']);
