<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CorreoPrincipal>
 */
class CorreoPrincipalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'correo' => $this->faker->email(),
            'contrasena' => Hash::make('password123'),
            'disponible' =>  $this->faker->boolean(),
        ];
    }
}
