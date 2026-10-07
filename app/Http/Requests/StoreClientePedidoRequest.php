<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientePedidoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['usuario' => Cliente::normalizarUsuario($this->input('usuario'))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('pedidos.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['nullable', 'string', 'max:120', 'required_without_all:telefono,usuario,email'],
            'codigo_pais' => ['nullable', 'string', 'max:5'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'usuario' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:clientes,email'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
