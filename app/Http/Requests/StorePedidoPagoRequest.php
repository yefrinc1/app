<?php

namespace App\Http\Requests;

use App\Services\ComprobantePagoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePedidoPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pagos.subir') ?? false;
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', 'string', 'max:50'],
            'valor_bruto' => ['required', 'numeric', 'min:0.01'],
            'moneda' => ['required', 'in:COP,USD'],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comprobante.required' => 'Debes adjuntar el comprobante para registrar el método de pago.',
            'comprobante.mimes' => 'El comprobante debe ser JPG, PNG, WEBP o PDF.',
            'comprobante.max' => 'El comprobante no puede superar 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('referencia') || $validator->errors()->has('comprobante')) {
                return;
            }

            try {
                app(ComprobantePagoService::class)->validarUnico(
                    $this->input('referencia'),
                    $this->file('comprobante')
                );
            } catch (\Illuminate\Validation\ValidationException $exception) {
                foreach ($exception->errors() as $campo => $mensajes) {
                    foreach ($mensajes as $mensaje) {
                        $validator->errors()->add($campo, $mensaje);
                    }
                }
            }
        });
    }
}
