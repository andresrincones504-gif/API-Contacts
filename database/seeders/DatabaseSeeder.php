<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $ana = User::factory()->create([
            'name' => 'Ana Pérez',
            'email' => 'ana@contacts.test',
        ]);

        $luis = User::factory()->create([
            'name' => 'Luis Gómez',
            'email' => 'luis@contacts.test',
        ]);

        Contact::factory(5)->for($ana)->create();
        Contact::factory(3)->for($luis)->create();
    }
}
