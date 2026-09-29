<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevisarPedidoReembolsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reembolsos.revisar') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo_rechazo' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
