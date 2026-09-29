<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoReembolsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reembolsos.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'pedido_pago_id' => ['nullable', 'integer', 'exists:pedido_pagos,id'],
            'pedido_detalle_id' => ['nullable', 'integer', 'exists:pedido_detalles,id'],
            'pedido_entrega_anulada_id' => ['nullable', 'integer', 'exists:pedido_entregas,id'],
            'cantidad' => ['nullable', 'required_with:pedido_detalle_id', 'integer', 'min:1'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'moneda' => ['required', 'in:COP,USD'],
            'metodo' => ['required', 'string', 'max:50'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'motivo' => ['required', 'string', 'min:5', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return [
            'valor.required' => 'Ingresa el valor que vas a reembolsar.',
            'valor.min' => 'El valor del reembolso debe ser mayor que cero.',
            'metodo.required' => 'Indica por qué método devolviste el dinero.',
            'motivo.required' => 'Escribe el motivo del reembolso.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'comprobante.mimes' => 'El comprobante debe ser JPG, PNG, WEBP o PDF.',
            'comprobante.max' => 'El comprobante no puede superar 10 MB.',
            'cantidad.required_with' => 'Indica cuántas unidades del juego ya no se entregarán.',
            'cantidad.min' => 'La cantidad reembolsada debe ser al menos 1.',
            'pedido_entrega_anulada_id.exists' => 'La entrega anulada seleccionada ya no existe.',
        ];
    }
}
