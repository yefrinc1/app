<?php

namespace App\Http\Requests;

use App\Models\PedidoPago;
use App\Services\ComprobantePagoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pedidos.crear') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cliente_nuevo.telefono')) {
            $cliente = $this->input('cliente_nuevo');
            $cliente['telefono'] = preg_replace('/\D/', '', $cliente['telefono']);
            $this->merge(['cliente_nuevo' => $cliente]);
        }
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'required_without:cliente_nuevo'],
            'cliente_nuevo' => ['nullable', 'array', 'required_without:cliente_id'],
            'cliente_nuevo.nombre' => ['nullable', 'string', 'max:120'],
            'cliente_nuevo.codigo_pais' => ['nullable', 'string', 'max:5'],
            'cliente_nuevo.telefono' => ['nullable', 'string', 'max:20'],
            'cliente_nuevo.usuario' => ['nullable', 'string', 'max:100'],
            'cliente_nuevo.email' => ['nullable', 'email', 'max:150', 'unique:clientes,email'],
            'cliente_nuevo.notas' => ['nullable', 'string', 'max:2000'],
            'canal_venta' => ['required', Rule::in(['manual', 'instagram', 'whatsapp', 'jumpseller', 'otro'])],
            'moneda' => ['required', Rule::in(['COP', 'USD'])],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.juego' => ['required', 'string', 'max:255'],
            'detalles.*.tipo_cuenta' => ['required', Rule::in(['Primaria', 'Secundaria'])],
            'detalles.*.consola' => ['required', Rule::in(['PS4', 'PS5'])],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:20'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:1000'],
            'pagos' => ['nullable', 'array'],
            'pagos.*.metodo_pago' => ['required', 'string', 'max:50'],
            'pagos.*.valor_bruto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.fecha_pago' => ['nullable', 'date'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:150'],
            'pagos.*.comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'pagos.*.observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required_without' => 'Selecciona un cliente o registra uno nuevo.',
            'cliente_nuevo.required_without' => 'Selecciona un cliente o registra uno nuevo.',
            'detalles.required' => 'Agrega al menos un juego al pedido.',
            'detalles.*.juego.required' => 'Selecciona el juego.',
            'pagos.*.comprobante.required' => 'Cada método de pago registrado debe tener un comprobante adjunto.',
            'pagos.*.comprobante.mimes' => 'El comprobante debe ser JPG, PNG, WEBP o PDF.',
            'pagos.*.comprobante.max' => 'El comprobante no puede superar 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->input('cliente_id') && $this->has('cliente_nuevo')) {
                $nuevo = $this->input('cliente_nuevo', []);
                $identidad = collect(['nombre', 'telefono', 'usuario', 'email'])
                    ->contains(fn ($campo) => filled($nuevo[$campo] ?? null));

                if (! $identidad) {
                    $validator->errors()->add(
                        'cliente_nuevo.nombre',
                        'Ingresa al menos nombre, teléfono, usuario o correo del nuevo cliente.'
                    );
                }
            }

            $servicio = app(ComprobantePagoService::class);
            $referenciasSolicitud = [];
            $hashesSolicitud = [];

            foreach ($this->input('pagos', []) as $indice => $pago) {
                $campoReferencia = "pagos.{$indice}.referencia";
                $campoComprobante = "pagos.{$indice}.comprobante";

                if ($validator->errors()->has($campoReferencia) || $validator->errors()->has($campoComprobante)) {
                    continue;
                }

                $referencia = $servicio->normalizarReferencia($pago['referencia'] ?? null);
                $hash = $servicio->hashArchivo($this->file($campoComprobante));

                if ($referencia && isset($referenciasSolicitud[$referencia])) {
                    $validator->errors()->add($campoReferencia, 'Esta referencia está repetida en los pagos del pedido que estás creando.');
                } elseif ($referencia && PedidoPago::where('referencia_normalizada', $referencia)->exists()) {
                    $validator->errors()->add($campoReferencia, 'Esta referencia ya fue registrada en otro comprobante.');
                }

                if ($hash && isset($hashesSolicitud[$hash])) {
                    $validator->errors()->add($campoComprobante, 'Esta misma imagen está repetida en los pagos del pedido que estás creando.');
                } elseif ($hash && PedidoPago::where('comprobante_hash', $hash)->exists()) {
                    $validator->errors()->add($campoComprobante, 'Este mismo comprobante ya fue subido anteriormente.');
                }

                if ($referencia) $referenciasSolicitud[$referencia] = true;
                if ($hash) $hashesSolicitud[$hash] = true;
            }
        });
    }
}
