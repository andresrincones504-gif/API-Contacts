<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name_visible ? $this->name : null,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'address' => $this->address_visible ? $this->address : null,
            'name_visible' => $this->name_visible,
            'address_visible' => $this->address_visible,
        ];
    }
}
