# API de Contactos

API REST en Laravel con autenticación por token (Sanctum) para gestionar
contactos personales.

## Requisitos

- PHP 8.2 o superior y Composer
- MySQL

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configura DB_DATABASE, DB_USERNAME y DB_PASSWORD en .env
php artisan migrate --seed
php artisan serve
```

Usuarios de ejemplo (contraseña `password`): `ana@contacts.test` y
`luis@contacts.test`.

## Pruebas

Crea una base `contacts_api_test` y ejecuta:

```bash
php artisan test
```

## Autenticación

1. `POST /api/v1/auth/login` con `email`, `password` y `device_name`.
2. Guarda el token de la respuesta.
3. Envíalo en cada petición protegida:

```
Authorization: Bearer {token}
Accept: application/json
```

El login admite 5 intentos por minuto por correo e IP.

## Endpoints

| Método | Ruta | Descripción | Auth |
|--------|------|-------------|------|
| GET | /api/v1/health | Estado del servicio | No |
| POST | /api/v1/auth/login | Inicia sesión y entrega un token | No |
| GET | /api/v1/auth/me | Usuario autenticado | Sí |
| POST | /api/v1/auth/logout | Cierra sesión y revoca el token | Sí |
| GET | /api/v1/profile | Perfil del usuario | Sí |
| PUT | /api/v1/profile | Actualiza el perfil del usuario | Sí |
| GET | /api/v1/contacts | Lista los contactos propios (paginado) | Sí |
| POST | /api/v1/contacts | Crea un contacto | Sí |
| GET | /api/v1/contacts/{id} | Perfil del contacto | Sí |
| PUT | /api/v1/contacts/{id} | Actualiza un contacto propio | Sí |
| DELETE | /api/v1/contacts/{id} | Elimina un contacto (soft delete) | Sí |

## Reglas

- El correo y el teléfono no se pueden repetir entre usuarios y contactos.
  Los contactos eliminados liberan su correo y teléfono.
- `name_visible` y `address_visible` ocultan el nombre y la dirección en el
  perfil del contacto (se devuelven como `null`).
- Un contacto de otro usuario responde `403`; uno que no existe, `404`.
- Sin token, las rutas protegidas responden `401` en JSON.

## Códigos de respuesta

| Código | Significado |
|--------|-------------|
| 200 / 201 / 204 | Correcto / creado / sin contenido |
| 401 | No autenticado |
| 403 | El recurso es de otro usuario |
| 404 | La ruta o el recurso no existe |
| 422 | Error de validación (campo por campo en `errors`) |
| 429 | Demasiados intentos de login |