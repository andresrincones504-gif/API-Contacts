<?php

namespace App\Rules;

use App\Models\Contact;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class UniqueIdentity implements ValidationRule
{
    /**
     * @param  string  $column  'email' o 'phone_number'
     * @param  Model|null  $ignore  El registro que se está editando (para no chocar consigo mismo)
     */
    public function __construct(
        private string $column,
        private ?Model $ignore = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $users = User::where($this->column, $value);
        // Contact usa SoftDeletes: los contactos eliminados no cuentan.
        $contacts = Contact::where($this->column, $value);

        if ($this->ignore instanceof User) {
            $users->whereKeyNot($this->ignore->getKey());
        }

        if ($this->ignore instanceof Contact) {
            $contacts->whereKeyNot($this->ignore->getKey());
        }

        if ($users->exists() || $contacts->exists()) {
            $fail($this->column === 'email'
                ? 'El correo ya está en uso.'
                : 'El teléfono ya está en uso.');
        }
    }
}
