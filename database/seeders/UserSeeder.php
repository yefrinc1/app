<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Yefrin Castaño',
            'email' => 'yefrincs10@gmail.com',
            'password' => bcrypt('Ycs654321*'),
        ])->assignRole('Administrador');
    }
}
