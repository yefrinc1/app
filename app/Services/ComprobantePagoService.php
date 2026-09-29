<?php

namespace App\Services;

use App\Models\PedidoPago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComprobantePagoService
{
    public function normalizarReferencia(?string $referencia): ?string
    {
        if ($referencia === null || trim($referencia) === '') {
            return null;
        }

        $normalizada = preg_replace('/[^A-Z0-9]/u', '', Str::upper(trim($referencia)));

        return $normalizada !== '' ? $normalizada : null;
    }

    public function hashArchivo(?UploadedFile $archivo): ?string
    {
        if (! $archivo || ! $archivo->isValid()) {
            return null;
        }

        return hash_file('sha256', $archivo->getRealPath());
    }

    public function validarUnico(
        ?string $referencia,
        ?UploadedFile $archivo,
        string $campoReferencia = 'referencia',
        string $campoComprobante = 'comprobante'
    ): array {
        $referenciaNormalizada = $this->normalizarReferencia($referencia);
        $comprobanteHash = $this->hashArchivo($archivo);

        $errores = [];

        if ($referenciaNormalizada) {
            $pago = PedidoPago::query()
                ->where('referencia_normalizada', $referenciaNormalizada)
                ->with('pedido:id,codigo')
                ->first();

            if ($pago) {
                $pedido = $pago->pedido?->codigo ?? "#{$pago->pedido_id}";
                $errores[$campoReferencia] = "Esta referencia ya fue registrada en el pedido {$pedido}.";
            }
        }

        if ($comprobanteHash) {
            $pago = PedidoPago::query()
                ->where('comprobante_hash', $comprobanteHash)
                ->with('pedido:id,codigo')
                ->first();

            if ($pago) {
                $pedido = $pago->pedido?->codigo ?? "#{$pago->pedido_id}";
                $errores[$campoComprobante] = "Este mismo comprobante ya fue subido en el pedido {$pedido}.";
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return [$referenciaNormalizada, $comprobanteHash];
    }
}
