<?php

use App\Models\Contact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('exige sesión para ver y editar el perfil', function () {
    $this->getJson('/api/v1/profile')->assertUnauthorized();
    $this->putJson('/api/v1/profile', ['name' => 'Nombre Nuevo'])->assertUnauthorized();
});

test('muestra el perfil del usuario autenticado', function () {
    // Preparar
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson('/api/v1/profile');

    // Comprobar
    $response->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.phone_number', $user->phone_number)
        ->assertJsonMissingPath('data.password');
});

test('actualiza el perfil del usuario', function () {
    // Preparar
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar: cambia datos y oculta la dirección; luego guarda con su propio correo y teléfono
    $cambio = $this->putJson('/api/v1/profile', [
        'name' => 'Nombre Nuevo',
        'address' => 'Carrera 1 # 2-3',
        'address_visible' => false,
    ]);
    $mismosDatos = $this->putJson('/api/v1/profile', [
        'email' => $user->email,
        'phone_number' => $user->phone_number,
    ]);

    // Comprobar
    $cambio->assertOk()->assertJsonPath('data.name', 'Nombre Nuevo');
    $mismosDatos->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Nombre Nuevo',
        'address' => 'Carrera 1 # 2-3',
        'address_visible' => false,
    ]);
});

test('rechaza un correo o teléfono que ya usa otro usuario o contacto', function () {
    // Preparar
    $user = User::factory()->create();
    $otroUsuario = User::factory()->create();
    $contacto = Contact::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $correoDeUsuario = $this->putJson('/api/v1/profile', ['email' => $otroUsuario->email]);
    $correoDeContacto = $this->putJson('/api/v1/profile', ['email' => $contacto->email]);
    $telefonoDeUsuario = $this->putJson('/api/v1/profile', ['phone_number' => $otroUsuario->phone_number]);
    $telefonoDeContacto = $this->putJson('/api/v1/profile', ['phone_number' => $contacto->phone_number]);

    // Comprobar: el perfil no cambió
    $correoDeUsuario->assertUnprocessable()->assertInvalid(['email']);
    $correoDeContacto->assertUnprocessable()->assertInvalid(['email']);
    $telefonoDeUsuario->assertUnprocessable()->assertInvalid(['phone_number']);
    $telefonoDeContacto->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => $user->email,
        'phone_number' => $user->phone_number,
    ]);
});

test('rechaza un correo con formato inválido', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->putJson('/api/v1/profile', ['email' => 'no-es-un-correo']);

    $response->assertUnprocessable()->assertInvalid(['email']);
});