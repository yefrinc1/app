<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_anulaciones') || ! Schema::hasColumn('venta_anulaciones', 'datos_inventario')) {
            return;
        }

        $puedeRepararReembolsos = Schema::hasTable('pedido_reembolsos')
            && Schema::hasColumn('pedido_reembolsos', 'pedido_entrega_anulada_id');

        DB::table('venta_anulaciones')
            ->whereNotNull('datos_inventario')
            ->orderBy('id')
            ->chunkById(100, function ($anulaciones) use ($puedeRepararReembolsos) {
                foreach ($anulaciones as $anulacion) {
                    $datos = json_decode((string) $anulacion->datos_inventario, true);
                    $venta = is_array($datos) ? ($datos['venta_eliminada'] ?? null) : null;
                    $precioBruto = is_array($venta) ? ($venta['precio_bruto_pedido'] ?? null) : null;

                    if ($precioBruto === null || ! is_numeric($precioBruto)) {
                        continue;
                    }

                    $precioBruto = max(0, (int) round((float) $precioBruto));
                    if ((int) $anulacion->valor_anulado !== $precioBruto) {
                        DB::table('venta_anulaciones')
                            ->where('id', $anulacion->id)
                            ->update(['valor_anulado' => $precioBruto]);
                    }

                    // Si la etapa 13 ya creo una solicitud pendiente usando el
                    // neto, se corrige al bruto. Los reembolsos aprobados no se
                    // alteran porque el dinero ya pudo haber sido entregado.
                    $precioNeto = is_array($venta)
                        ? ($venta['precio_neto'] ?? $venta['precio'] ?? null)
                        : null;
                    if (
                        $puedeRepararReembolsos
                        && $precioNeto !== null
                        && is_numeric($precioNeto)
                    ) {
                        DB::table('pedido_reembolsos')
                            ->where('pedido_entrega_anulada_id', $anulacion->pedido_entrega_id)
                            ->where('estado', 'pendiente')
                            ->where('valor', (float) $precioNeto)
                            ->update(['valor' => $precioBruto]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Reparacion de datos: no se revierte para no volver a reducir el valor
        // reembolsable de anulaciones creadas con ventas netas.
    }
};
