<?php

use App\Models\Contact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('inicia sesión y entrega un token con credenciales correctas', function () {
    // Preparar
    $user = User::factory()->create([
        'email' => 'ana@contacts.test',
        'password' => 'secreta123',
    ]);

    // Actuar
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ana@contacts.test',
        'password' => 'secreta123',
        'device_name' => 'pruebas',
    ]);

    // Comprobar
    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.email', 'ana@contacts.test')
        ->assertJsonMissingPath('user.password');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'pruebas',
    ]);
});

test('rechaza el login con credenciales incorrectas', function () {
    // Preparar
    User::factory()->create([
        'email' => 'ana@contacts.test',
        'password' => 'secreta123',
    ]);

    // Actuar: contraseña incorrecta y correo inexistente
    $contrasenaIncorrecta = $this->postJson('/api/v1/auth/login', [
        'email' => 'ana@contacts.test',
        'password' => 'otra-contraseña',
        'device_name' => 'pruebas',
    ]);
    $correoInexistente = $this->postJson('/api/v1/auth/login', [
        'email' => 'nadie@contacts.test',
        'password' => 'secreta123',
        'device_name' => 'pruebas',
    ]);

    // Comprobar: el mismo mensaje en ambos casos y ningún token creado
    $contrasenaIncorrecta->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');
    $correoInexistente->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('exige correo, contraseña y nombre de dispositivo para iniciar sesión', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable()
        ->assertInvalid(['email', 'password', 'device_name']);
});

test('bloquea el login al sexto intento fallido en un minuto', function () {
    // Preparar
    User::factory()->create(['email' => 'ana@contacts.test']);
    $intentoFallido = [
        'email' => 'ana@contacts.test',
        'password' => 'incorrecta',
        'device_name' => 'pruebas',
    ];

    // Actuar: cinco intentos permitidos
    foreach (range(1, 5) as $intento) {
        $this->postJson('/api/v1/auth/login', $intentoFallido)->assertUnprocessable();
    }

    // Comprobar: el sexto se bloquea
    $this->postJson('/api/v1/auth/login', $intentoFallido)->assertTooManyRequests();
});

test('exige token para ver el usuario autenticado', function () {
    // Con y sin la cabecera Accept
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();

    $this->get('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json');
});

test('muestra el usuario autenticado', function () {
    // Preparar
    $user = User::factory()->create(['email' => 'ana@contacts.test']);
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson('/api/v1/auth/me');

    // Comprobar
    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'ana@contacts.test')
        ->assertJsonMissingPath('data.password');
});

test('cierra sesión y revoca el token', function () {
    // Preparar
    $user = User::factory()->create();
    $token = $user->createToken('pruebas')->plainTextToken;

    // Sin token no se puede cerrar sesión, y el token existente no se toca
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    $this->assertDatabaseCount('personal_access_tokens', 1);

    // Actuar
    $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

    // Comprobar
    $response->assertNoContent();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('registra un usuario y entrega un token', function () {
    // Actuar
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana Pérez',
        'email' => 'ana@contacts.test',
        'phone_number' => '3105551234',
        'password' => 'secreta123',
        'password_confirmation' => 'secreta123',
        'device_name' => 'pruebas',
    ]);

    // Comprobar
    $response->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.email', 'ana@contacts.test')
        ->assertJsonMissingPath('user.password');

    $this->assertDatabaseHas('users', [
        'email' => 'ana@contacts.test',
        'phone_number' => '3105551234',
    ]);
    $this->assertDatabaseHas('personal_access_tokens', ['name' => 'pruebas']);
});

test('rechaza un registro con datos inválidos o repetidos', function () {
    // Preparar
    User::factory()->create([
        'email' => 'usuario@correo.test',
        'phone_number' => '3001110000',
    ]);
    Contact::factory()->create([
        'email' => 'contacto@correo.test',
        'phone_number' => '3002220000',
    ]);

    $datos = fn (array $cambios = []) => array_merge([
        'name' => 'Ana Pérez',
        'email' => 'nuevo@correo.test',
        'phone_number' => '3105551234',
        'password' => 'secreta123',
        'password_confirmation' => 'secreta123',
        'device_name' => 'pruebas',
    ], $cambios);

    // Actuar y comprobar
    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertInvalid(['name', 'email', 'phone_number', 'password', 'device_name']);

    $this->postJson('/api/v1/auth/register', $datos(['email' => 'usuario@correo.test']))
        ->assertUnprocessable()->assertInvalid(['email']);
    $this->postJson('/api/v1/auth/register', $datos(['email' => 'contacto@correo.test']))
        ->assertUnprocessable()->assertInvalid(['email']);
    $this->postJson('/api/v1/auth/register', $datos(['phone_number' => '3001110000']))
        ->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->postJson('/api/v1/auth/register', $datos(['phone_number' => '3002220000']))
        ->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->postJson('/api/v1/auth/register', $datos(['password_confirmation' => 'otra']))
        ->assertUnprocessable()->assertInvalid(['password']);
    $this->postJson('/api/v1/auth/register', $datos(['password' => '123', 'password_confirmation' => '123']))
        ->assertUnprocessable()->assertInvalid(['password']);

    // Ningún intento creó el usuario nuevo
    $this->assertDatabaseMissing('users', ['email' => 'nuevo@correo.test']);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});