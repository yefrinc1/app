<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoLiquidacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pagos.revisar') ?? false;
    }

    public function rules(): array
    {
        return [
            'valor_bruto' => ['required', 'numeric', 'min:0.01'],
            'comision' => ['nullable', 'numeric', 'min:0'],
            'retencion' => ['nullable', 'numeric', 'min:0'],
            'otros_descuentos' => ['nullable', 'numeric', 'min:0'],
            'fecha_liquidacion' => ['required', 'date'],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
