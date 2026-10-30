<?php

namespace App\Services;

use App\Models\CorreoJuego;
use App\Models\Notificaciones;
use Illuminate\Support\Facades\Log;
use Throwable;

class JumpsellerAgotadoService
{
    public function __construct(private JumpsellerApiService $jumpseller)
    {
    }

    /**
     * Si ya no hay inventario para esa consola y licencia, actualiza Jumpseller.
     * Primaria: quita el precio de oferta y deja el precio regular.
     * Secundaria: elimina siempre la opción, tenga o no oferta.
     */
    public function sincronizarSiAgotado(string $juego, string $tipoCuenta, string $consola): void
    {
        if ($this->existeInventario($tipoCuenta, $consola, $juego)) {
            return;
        }

        try {
            $this->actualizarProductoAgotado($juego, $tipoCuenta, $consola);
        } catch (Throwable $exception) {
            Log::error('No se pudo actualizar Jumpseller al agotar inventario.', [
                'juego' => $juego,
                'tipo_cuenta' => $tipoCuenta,
                'consola' => $consola,
                'error' => $exception->getMessage(),
            ]);

            $this->notificar(
                'agotado_juego',
                $juego,
                "Se agotaron las cuentas {$tipoCuenta} para {$consola}. No se pudo actualizar Jumpseller."
            );
        }
    }

    public function sincronizarAgotados(array $combos): void
    {
        $unicos = [];

        foreach ($combos as $combo) {
            $clave = ($combo['juego'] ?? '').'|'.($combo['tipo_cuenta'] ?? '').'|'.($combo['consola'] ?? '');

            if ($clave === '||' || isset($unicos[$clave])) {
                continue;
            }

            $unicos[$clave] = [
                'juego' => $combo['juego'],
                'tipo_cuenta' => $combo['tipo_cuenta'],
                'consola' => $combo['consola'],
            ];
        }

        foreach ($unicos as $combo) {
            $this->sincronizarSiAgotado($combo['juego'], $combo['tipo_cuenta'], $combo['consola']);
        }
    }

    public function existeInventario(string $tipoCuenta, string $consola, string $juego): bool
    {
        if ($tipoCuenta === 'Primaria') {
            $licencia = $consola === 'PS4' ? 'primaria_ps4' : 'primaria_ps5';

            return CorreoJuego::query()
                ->where('juego', $juego)
                ->where($licencia, '<', 2)
                ->where('disponible', 1)
                ->exists();
        }

        if ($tipoCuenta === 'Secundaria') {
            $primaria = $consola === 'PS4' ? 'primaria_ps4' : 'primaria_ps5';

            return CorreoJuego::query()
                ->where('juego', $juego)
                ->where($primaria, '=', 2)
                ->where('secundaria', '=', 0)
                ->where('disponible', 1)
                ->exists();
        }

        return false;
    }

    private function actualizarProductoAgotado(string $juego, string $tipoCuenta, string $consola): void
    {
        $productos = $this->jumpseller->searchProductos($juego, ['id', 'name'], 1);
        $producto = $productos[0]['product'] ?? null;

        if (! $producto || ($producto['name'] ?? null) !== $juego) {
            $this->notificar(
                'agotado_juego',
                $juego,
                "Se agotaron las cuentas {$tipoCuenta} para {$consola}"
            );

            return;
        }

        $idProducto = $producto['id'];
        $ids = $this->idsOpciones();

        if ($tipoCuenta === 'Secundaria') {
            $this->quitarOpcionSecundaria($idProducto, $juego, $tipoCuenta, $consola, $ids);

            return;
        }

        $this->quitarPrecioOferta($idProducto, $juego, $tipoCuenta, $consola, $ids);
    }

    private function quitarPrecioOferta(int|string $idProducto, string $juego, string $tipoCuenta, string $consola, array $ids): void
    {
        $producto = $this->jumpseller->getProductoId($idProducto);
        $variantes = $producto['product']['variants'] ?? [];
        $idCuentaObjetivo = $ids['primaria'];
        $idConsolaObjetivo = $consola === 'PS4' ? $ids['ps4'] : $ids['ps5'];
        $variantesParaApi = [];
        $actualizoPrecio = false;

        foreach ($variantes as $variante) {
            $precioCompare = $variante['compare_at_price'] ?? null;
            $idVariante = $variante['id'] ?? null;
            $cuenta = null;
            $consolaVariante = null;

            foreach ($variante['options'] ?? [] as $opcion) {
                if (($opcion['product_option_id'] ?? null) == $ids['cuenta']) {
                    $cuenta = $opcion['product_option_value_id'] ?? null;
                }
                if (($opcion['product_option_id'] ?? null) == $ids['consola']) {
                    $consolaVariante = $opcion['product_option_value_id'] ?? null;
                }
            }

            if ($cuenta == $idCuentaObjetivo && $consolaVariante == $idConsolaObjetivo && $precioCompare != null) {
                $variantesParaApi[] = [
                    'id' => $idVariante,
                    'price' => $precioCompare,
                    'compare_at_price' => null,
                ];
                $actualizoPrecio = true;
            } else {
                $variantesParaApi[] = ['id' => $idVariante];
            }
        }

        if (! $actualizoPrecio) {
            $this->notificar(
                'agotado_juego',
                $juego,
                "Se agotaron las cuentas {$tipoCuenta} para {$consola}"
            );

            return;
        }

        $this->jumpseller->updateProducto($idProducto, [
            'product' => [
                'variants' => $variantesParaApi,
            ],
        ]);

        $this->notificar(
            'precio_modificado',
            $juego,
            "Se quito las cuentas {$tipoCuenta} para {$consola} para el juego {$juego} en Jumpseller ✅"
        );
    }

    private function quitarOpcionSecundaria(int|string $idProducto, string $juego, string $tipoCuenta, string $consola, array $ids): void
    {
        $resultadoOpciones = $this->jumpseller->getProductoOptionValues($idProducto, $ids['cuenta']);
        $existeOpcion = false;

        foreach ($resultadoOpciones ?? [] as $optionValue) {
            if (($optionValue['value']['id'] ?? null) == $ids['secundaria']) {
                $existeOpcion = true;
                break;
            }
        }

        if ($existeOpcion) {
            $this->jumpseller->deleteProductoOptionValue($idProducto, $ids['cuenta'], $ids['secundaria']);
            $this->notificar(
                'precio_modificado',
                $juego,
                "Se quito las cuentas {$tipoCuenta} para {$consola} para el juego {$juego} en Jumpseller ✅"
            );

            return;
        }

        $this->notificar(
            'agotado_juego',
            $juego,
            "Se agotaron las cuentas {$tipoCuenta} para {$consola}"
        );
    }

    private function idsOpciones(): array
    {
        return [
            'cuenta' => env('JUMPSELLER_CUENTA_OPTION_ID'),
            'primaria' => env('JUMPSELLER_PRIMARIA_OPTION_VALUE_ID'),
            'secundaria' => env('JUMPSELLER_SECUNDARIA_OPTION_VALUE_ID'),
            'consola' => env('JUMPSELLER_CONSOLA_OPTION_ID'),
            'ps4' => env('JUMPSELLER_PS4_OPTION_VALUE_ID'),
            'ps5' => env('JUMPSELLER_PS5_OPTION_VALUE_ID'),
        ];
    }

    private function notificar(string $tipo, string $juego, string $mensaje): void
    {
        Notificaciones::create([
            'tipo' => $tipo,
            'juego' => $juego,
            'mensaje' => $mensaje,
        ]);
    }
}
