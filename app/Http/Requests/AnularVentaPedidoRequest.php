<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnularVentaPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pedidos.anular') ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['reutilizable', 'no_reutilizable'])],
            'motivo' => ['required', 'string', 'min:5', 'max:1500'],
        ];
    }
}
