<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Administrador',
            'email' => 'administrador@mrjuegoz.com',
            'password' => bcrypt('Cambiar123!'),
        ])->assignRole('administrador');
    }
}
