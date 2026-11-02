<?php

namespace App\Services;

use App\Models\CodigoVerificacion;
use App\Models\Notificaciones;
use App\Models\PedidoEntrega;
use App\Models\PortalCodigoSolicitud;
use App\Models\PortalJuegoAcceso;
use App\Models\Ventas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalCodigoService
{
    public function estado(int $clienteId, string $origen, int $id, string $estadoGarantia, bool $saltarEvidencia = false): array
    {
        $cuenta = $this->cuenta($clienteId, $origen, $id);
        $ultimaCliente = $this->ultimaSolicitudCliente($clienteId, $cuenta);
        $yaPidioHoy = $this->solicitudesHoy($clienteId, $cuenta) > 0;
        $solicitudHoy = $yaPidioHoy ? $ultimaCliente : null;
        $mostrarInstalacion = $estadoGarantia !== 'activa' && ! $saltarEvidencia && ! $ultimaCliente && filled($cuenta['codigo_instalacion']);
        $hayStock = $this->hayStock($cuenta['correo_juego_id']);
        $esPrimaria = $this->esPrimaria($cuenta);
        $visible = $mostrarInstalacion ? $cuenta['codigo_instalacion'] : $solicitudHoy?->codigo;

        return [
            'visible' => $visible,
            'es_instalacion' => $mostrarInstalacion,
            'es_primaria' => $esPrimaria,
            'solicitado_at' => $solicitudHoy?->created_at?->toIso8601String(),
            'puede_solicitar' => $esPrimaria && (bool) $cuenta['correo_juego_id'] && $hayStock && ! $yaPidioHoy,
            'sin_stock' => $esPrimaria && (bool) $cuenta['correo_juego_id'] && ! $hayStock,
            'tiene_correo' => (bool) $cuenta['correo_juego_id'],
        ];
    }

    public function solicitar(int $clienteId, int $userId, string $origen, int $id, ?string $ip, ?string $userAgent): PortalCodigoSolicitud
    {
        $cuenta = $this->cuenta($clienteId, $origen, $id);

        if (! $this->esPrimaria($cuenta)) {
            throw ValidationException::withMessages([
                'codigo' => 'En una cuenta secundaria el candado se quita con el video. No necesitas un código nuevo.',
            ]);
        }

        if (! $cuenta['correo_juego_id']) {
            throw ValidationException::withMessages([
                'codigo' => 'Este juego no tiene una cuenta asociada para generar códigos.',
            ]);
        }

        if ($this->solicitudesHoy($clienteId, $cuenta) > 0) {
            throw ValidationException::withMessages([
                'codigo' => 'Ya tienes un código en esta pantalla. Úsalo ahora; es de un solo uso.',
            ]);
        }

        $resultado = DB::transaction(function () use ($clienteId, $userId, $cuenta, $ip, $userAgent) {
            if ($this->solicitudesHoy($clienteId, $cuenta) > 0) {
                throw ValidationException::withMessages([
                    'codigo' => 'Ya tienes un código en esta pantalla. Úsalo ahora; es de un solo uso.',
                ]);
            }

            $codigo = CodigoVerificacion::query()
                ->where('id_correo_juego', $cuenta['correo_juego_id'])
                ->where('disponible', 1)
                ->where('respaldo', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $codigo) {
                return ['sin_stock' => true];
            }

            $codigo->update(['disponible' => 0]);

            return [
                'solicitud' => PortalCodigoSolicitud::create([
                    'cliente_id' => $clienteId,
                    'user_id' => $userId,
                    'correo_juego_id' => $cuenta['correo_juego_id'],
                    'pedido_entrega_id' => $cuenta['pedido_entrega_id'],
                    'venta_id' => $cuenta['venta_id'],
                    'codigo_verificacion_id' => $codigo->id,
                    'codigo' => $codigo->codigo,
                    'ip' => $ip,
                    'user_agent' => mb_substr((string) $userAgent, 0, 1000),
                ]),
                'agotado' => ! $this->hayStock($cuenta['correo_juego_id']),
            ];
        });

        if ($resultado['sin_stock'] ?? false) {
            $this->notificarFaltaCodigos($cuenta['correo_juego_id']);

            throw ValidationException::withMessages([
                'codigo' => 'Estamos recargando códigos para esta cuenta. En unos minutos vuelve a intentar o escribe a soporte.',
            ]);
        }

        if ($resultado['agotado'] ?? false) {
            $this->notificarFaltaCodigos($cuenta['correo_juego_id']);
        }

        $solicitud = $resultado['solicitud'];

        PortalJuegoAcceso::create([
            'cliente_id' => $clienteId,
            'user_id' => $userId,
            'pedido_entrega_id' => $cuenta['pedido_entrega_id'],
            'venta_id' => $cuenta['venta_id'],
            'accion' => 'solicitar_codigo',
            'ip' => $ip,
            'user_agent' => mb_substr((string) $userAgent, 0, 1000),
        ]);

        return $solicitud;
    }

    private function cuenta(int $clienteId, string $origen, int $id): array
    {
        if ($origen === 'pedido') {
            $entrega = PedidoEntrega::query()
                ->whereKey($id)
                ->where('estado', '!=', 'anulada')
                ->whereHas('detalle.pedido', fn ($query) => $query->where('cliente_id', $clienteId))
                ->with(['codigoVerificacion:id,codigo', 'detalle:id,tipo_cuenta'])
                ->firstOrFail();

            return [
                'correo_juego_id' => $entrega->correo_juego_id,
                'pedido_entrega_id' => $entrega->id,
                'venta_id' => null,
                'tipo_cuenta' => $entrega->detalle?->tipo_cuenta,
                'codigo_instalacion' => $entrega->codigoVerificacion?->codigo,
            ];
        }

        if ($origen === 'historica') {
            $venta = Ventas::query()
                ->whereKey($id)
                ->where('cliente_id', $clienteId)
                ->whereNull('pedido_detalle_id')
                ->where(fn ($query) => $query->whereNull('estado')->orWhere('estado', 'activa'))
                ->firstOrFail();

            return [
                'correo_juego_id' => $venta->id_correo_juego,
                'pedido_entrega_id' => null,
                'venta_id' => $venta->id,
                'tipo_cuenta' => $venta->tipo_cuenta,
                'codigo_instalacion' => null,
            ];
        }

        abort(404);
    }

    private function esPrimaria(array $cuenta): bool
    {
        return Str::lower((string) ($cuenta['tipo_cuenta'] ?? '')) === 'primaria';
    }

    private function ultimaSolicitudCliente(int $clienteId, array $cuenta): ?PortalCodigoSolicitud
    {
        return PortalCodigoSolicitud::query()
            ->where('cliente_id', $clienteId)
            ->when($cuenta['pedido_entrega_id'], fn ($query, $id) => $query->where('pedido_entrega_id', $id))
            ->when($cuenta['venta_id'], fn ($query, $id) => $query->where('venta_id', $id))
            ->latest('id')
            ->first();
    }

    private function solicitudesHoy(int $clienteId, array $cuenta): int
    {
        return PortalCodigoSolicitud::query()
            ->where('cliente_id', $clienteId)
            ->when($cuenta['pedido_entrega_id'], fn ($query, $id) => $query->where('pedido_entrega_id', $id))
            ->when($cuenta['venta_id'], fn ($query, $id) => $query->where('venta_id', $id))
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    private function hayStock(?int $correoJuegoId): bool
    {
        if (! $correoJuegoId) {
            return false;
        }

        return CodigoVerificacion::query()
            ->where('id_correo_juego', $correoJuegoId)
            ->where('disponible', 1)
            ->where('respaldo', 0)
            ->exists();
    }

    private function notificarFaltaCodigos(int $correoJuegoId): void
    {
        $existe = Notificaciones::query()
            ->where('id_correo_juego', $correoJuegoId)
            ->where('tipo', 'crear_codigos')
            ->where('mensaje', 'Se necesita crear codigos')
            ->exists();

        if ($existe) {
            return;
        }

        Notificaciones::create([
            'id_correo_juego' => $correoJuegoId,
            'tipo' => 'crear_codigos',
            'mensaje' => 'Se necesita crear codigos',
        ]);
    }
}
