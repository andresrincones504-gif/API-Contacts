<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Http\Resources\ContactProfileResource;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    /**
     * Lista los contactos del usuario autenticado (nunca los de otros).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ContactResource::collection(
            $request->user()->contacts()->latest('id')->paginate(15)
        );
    }

    /**
     * Crea un contacto para el usuario autenticado.
     */
    public function store(StoreContactRequest $request): ContactResource
    {
        $contact = $request->user()->contacts()->create($request->validated());

        return new ContactResource($contact);
    }

    /**
     * Perfil del contacto (respeta nombre y dirección ocultos).
     */
    public function show(Contact $contact): ContactProfileResource
    {
        Gate::authorize('view', $contact);

        return new ContactProfileResource($contact);
    }

    /**
     * Actualiza un contacto propio.
     */
    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $contact->update($request->validated());

        return new ContactResource($contact);
    }

    /**
     * Elimina un contacto propio (borrado lógico).
     */
    public function destroy(Contact $contact): Response
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        return response()->noContent();
    }
}
