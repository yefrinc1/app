<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CorreoMadre>
 */
class CorreoMadreFactory extends Factory
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
            'id_correo_principal' => $this->faker->numberBetween(1, 14),
            'saldo_usd' => $this->faker->randomNumber(3),
            'fecha_nacimiento' => $this->faker->date(),
            'disponible' =>  $this->faker->boolean(),
        ];
    }
}
