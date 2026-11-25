<?php

use App\Models\Contact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('exige sesión en todos los endpoints de contactos', function () {
    // Preparar
    $contact = Contact::factory()->create(['name' => 'Nombre original']);

    // Actuar y comprobar: los cinco endpoints rechazan la petición
    $this->getJson('/api/v1/contacts')->assertUnauthorized();
    $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ])->assertUnauthorized();
    $this->getJson("/api/v1/contacts/{$contact->id}")->assertUnauthorized();
    $this->putJson("/api/v1/contacts/{$contact->id}", ['name' => 'Nombre nuevo'])->assertUnauthorized();
    $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertUnauthorized();

    // Nada cambió en la base
    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'Nombre original',
        'deleted_at' => null,
    ]);
});

test('lista solo los contactos propios y no los eliminados', function () {
    // Preparar
    $user = User::factory()->create();
    Contact::factory(3)->for($user)->create();
    Contact::factory()->for($user)->create()->delete();
    Contact::factory(2)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson('/api/v1/contacts');

    // Comprobar
    $response->assertOk()->assertJsonCount(3, 'data');
});

test('crea un contacto válido para el usuario autenticado', function () {
    // Preparar
    $user = User::factory()->create();
    $otroUsuario = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar: el cliente intenta asignar el contacto a otro usuario
    $response = $this->postJson('/api/v1/contacts', [
        'user_id' => $otroUsuario->id,
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ]);

    // Comprobar
    $response->assertCreated()
        ->assertJsonPath('data.name', 'Carlos Ruiz')
        ->assertJsonPath('data.phone_number', '3105551234')
        ->assertJsonPath('data.name_visible', true)
        ->assertJsonPath('data.address_visible', true);

    $this->assertDatabaseHas('contacts', [
        'user_id' => $user->id,
        'name' => 'Carlos Ruiz',
        'phone_number' => '3105551234',
    ]);
});

test('rechaza un contacto sin datos obligatorios o con teléfono inválido', function () {
    // Preparar
    Sanctum::actingAs(User::factory()->create());

    // Actuar
    $sinDatos = $this->postJson('/api/v1/contacts', []);
    $telefonoInvalido = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => 'abc123',
    ]);

    // Comprobar
    $sinDatos->assertUnprocessable()->assertInvalid(['name', 'email', 'phone_number']);
    $telefonoInvalido->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseCount('contacts', 0);
});

test('rechaza un correo o teléfono que ya usa otro usuario o contacto', function () {
    // Preparar
    User::factory()->create([
        'email' => 'usuario@correo.test',
        'phone_number' => '3001110000',
    ]);
    Contact::factory()->create([
        'email' => 'contacto@correo.test',
        'phone_number' => '3002220000',
    ]);
    Sanctum::actingAs(User::factory()->create());

    $datos = fn (array $cambios) => array_merge([
        'name' => 'Carlos Ruiz',
        'email' => 'nuevo@correo.test',
        'phone_number' => '3105551234',
    ], $cambios);

    // Actuar
    $correoDeUsuario = $this->postJson('/api/v1/contacts', $datos(['email' => 'usuario@correo.test']));
    $correoDeContacto = $this->postJson('/api/v1/contacts', $datos(['email' => 'contacto@correo.test']));
    $telefonoDeUsuario = $this->postJson('/api/v1/contacts', $datos(['phone_number' => '3001110000']));
    $telefonoDeContacto = $this->postJson('/api/v1/contacts', $datos(['phone_number' => '3002220000']));

    // Comprobar: ninguno se creó, solo existe el contacto original
    $correoDeUsuario->assertUnprocessable()->assertInvalid(['email']);
    $correoDeContacto->assertUnprocessable()->assertInvalid(['email']);
    $telefonoDeUsuario->assertUnprocessable()->assertInvalid(['phone_number']);
    $telefonoDeContacto->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseCount('contacts', 1);
});

test('permite reutilizar el correo y el teléfono de un contacto eliminado', function () {
    // Preparar
    Contact::factory()->create([
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ])->delete();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ]);

    // Comprobar
    $response->assertCreated();
    $this->assertDatabaseHas('contacts', [
        'user_id' => $user->id,
        'email' => 'carlos@correo.test',
        'deleted_at' => null,
    ]);
});

test('muestra el perfil de un contacto propio', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    // Comprobar
    $response->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.name', $contact->name)
        ->assertJsonPath('data.address', $contact->address);
});

test('oculta el nombre y la dirección en el perfil si están marcados como ocultos', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create([
        'name_visible' => false,
        'address_visible' => false,
    ]);
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    // Comprobar: nombre y dirección ocultos, el resto sigue visible
    $response->assertOk()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.email', $contact->email);
});

test('responde 404 en JSON si el contacto no existe o fue eliminado', function () {
    // Preparar
    $user = User::factory()->create();
    $eliminado = Contact::factory()->for($user)->create();
    $eliminado->delete();
    Sanctum::actingAs($user);

    // Actuar
    $inexistente = $this->getJson('/api/v1/contacts/99999');
    $borrado = $this->getJson("/api/v1/contacts/{$eliminado->id}");

    // Comprobar
    $inexistente->assertNotFound()->assertJsonPath('message', 'Recurso no encontrado.');
    $borrado->assertNotFound();
});

test('no deja ver, editar ni eliminar el contacto de otro usuario', function () {
    // Preparar
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $contact = Contact::factory()->for($owner)->create(['name' => 'Contacto privado']);
    Sanctum::actingAs($visitor);

    // Actuar
    $ver = $this->getJson("/api/v1/contacts/{$contact->id}");
    $editar = $this->putJson("/api/v1/contacts/{$contact->id}", ['name' => 'Nombre cambiado por otro']);
    $eliminar = $this->deleteJson("/api/v1/contacts/{$contact->id}");

    // Comprobar
    $ver->assertForbidden()->assertJsonMissing(['name' => 'Contacto privado']);
    $editar->assertForbidden();
    $eliminar->assertForbidden();

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'user_id' => $owner->id,
        'name' => 'Contacto privado',
        'deleted_at' => null,
    ]);
});

test('actualiza un contacto propio', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    // Actuar: cambia el nombre y oculta la dirección; luego guarda con sus propios correo y teléfono
    $cambio = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'name' => 'Nombre Nuevo',
        'address_visible' => false,
    ]);
    $mismosDatos = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'email' => $contact->email,
        'phone_number' => $contact->phone_number,
    ]);

    // Comprobar
    $cambio->assertOk()->assertJsonPath('data.name', 'Nombre Nuevo');
    $mismosDatos->assertOk();

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'Nombre Nuevo',
        'address_visible' => false,
    ]);
});

test('rechaza actualizar con el correo o el teléfono de otro contacto', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $otro = Contact::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $correoAjeno = $this->putJson("/api/v1/contacts/{$contact->id}", ['email' => $otro->email]);
    $telefonoAjeno = $this->putJson("/api/v1/contacts/{$contact->id}", ['phone_number' => $otro->phone_number]);

    // Comprobar
    $correoAjeno->assertUnprocessable()->assertInvalid(['email']);
    $telefonoAjeno->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'email' => $contact->email,
        'phone_number' => $contact->phone_number,
    ]);
});

test('elimina un contacto propio con borrado lógico', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

    // Comprobar: la fila sigue en la base, marcada como eliminada
    $response->assertNoContent();
    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
});