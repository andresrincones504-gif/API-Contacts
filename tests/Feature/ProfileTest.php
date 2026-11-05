<?php

use App\Models\Contact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('exige sesión para ver y editar el perfil', function (string $method) {
    $this->json($method, '/api/v1/profile')->assertUnauthorized();
})->with(['GET', 'PUT']);

it('muestra el perfil del usuario autenticado', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $this->user->email)
        ->assertJsonPath('data.phone_number', $this->user->phone_number)
        ->assertJsonMissingPath('data.password');
});

it('actualiza el perfil del usuario', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/profile', [
        'name' => 'Nombre Nuevo',
        'address' => 'Carrera 1 # 2-3',
        'address_visible' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nombre Nuevo');

    expect($this->user->fresh()->address)->toBe('Carrera 1 # 2-3')
        ->and($this->user->fresh()->address_visible)->toBeFalse();
});

it('permite guardar su propio correo y teléfono', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/profile', [
        'email' => $this->user->email,
        'phone_number' => $this->user->phone_number,
    ])->assertOk();
});

it('rechaza un correo o teléfono que ya usa otro usuario o contacto', function (string $campo, string $origen) {
    $otro = $origen === 'usuario' ? User::factory()->create() : Contact::factory()->create();
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/profile', [$campo => $otro->{$campo}])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo]);
})->with([
    ['email', 'usuario'],
    ['email', 'contacto'],
    ['phone_number', 'usuario'],
    ['phone_number', 'contacto'],
]);

it('valida el formato del correo', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/profile', ['email' => 'no-es-un-correo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
