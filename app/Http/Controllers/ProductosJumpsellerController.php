<?php

namespace App\Http\Controllers;

use App\Models\CorreoJuego;
use App\Services\JumpsellerApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductosJumpsellerController extends Controller
{
public function index(JumpsellerApiService $jumpseller)
{
    $numeroProductosOferta = $jumpseller->getProductosOfertaCount();
    $todosLosProductos = [];

    // Si hay productos, procesamos la paginación y el mapeo
    if ($numeroProductosOferta['count'] > 0) {
        $total = $numeroProductosOferta['count'];
        $porPagina = 100;
        $paginas = (int) ceil($total / $porPagina);

        // Cargamos los IDs de configuración una sola vez
        $config = [
            'idCuenta' => env('JUMPSELLER_CUENTA_OPTION_ID'),
            'idPrimaria' => env('JUMPSELLER_PRIMARIA_OPTION_VALUE_ID'),
            'idSecundaria' => env('JUMPSELLER_SECUNDARIA_OPTION_VALUE_ID'),
            'idConsola' => env('JUMPSELLER_CONSOLA_OPTION_ID'),
            'idPs4' => env('JUMPSELLER_PS4_OPTION_VALUE_ID'),
            'idPs5' => env('JUMPSELLER_PS5_OPTION_VALUE_ID'),
        ];

        for ($i = 1; $i <= $paginas; $i++) {
            $productos = $jumpseller->getProductosOferta($porPagina, $i);
            
            if (empty($productos)) continue;

            foreach ($productos as $producto) {
                $todosLosProductos[] = $this->mapearProductoOferta($producto, $config);
            }
        }
    }

    // Siempre retornamos la vista de Inertia, incluso si el array está vacío
    return Inertia::render('ProductosJumpseller/Ofertas', [
        'productos' => $todosLosProductos,
        'mensaje' => session('mensaje'),
        'tipo' => session('tipo', 'success'),
    ]);
}

/**
 * Método auxiliar para limpiar la lógica de variantes del index
 */
private function mapearProductoOferta($data, $config)
{
    $producto = $data['product'];
    $res = [
        'id' => $producto['id'],
        'nombre' => $producto['name'],
        'imagen' => $producto['images'][0]['url'] ?? null,
    ];

    // Inicializar precios en null
    foreach (['primaria_ps4', 'primaria_ps5', 'secundaria_ps4', 'secundaria_ps5'] as $key) {
        $res["precio_{$key}"] = null;
        $res["compare_{$key}"] = null;
    }

    foreach ($producto['variants'] as $variante) {
        $cuenta = null;
        $consola = null;

        foreach ($variante['options'] as $opcion) {
            if ($opcion['product_option_id'] == $config['idCuenta']) $cuenta = $opcion['product_option_value_id'];
            if ($opcion['product_option_id'] == $config['idConsola']) $consola = $opcion['product_option_value_id'];
        }

        // Mapeo dinámico de precios según cuenta y consola
        if ($cuenta == $config['idPrimaria'] && $consola == $config['idPs4']) {
            $res['precio_primaria_ps4'] = $variante['price'];
            $res['compare_primaria_ps4'] = $variante['compare_at_price'];
        } elseif ($cuenta == $config['idPrimaria'] && $consola == $config['idPs5']) {
            $res['precio_primaria_ps5'] = $variante['price'];
            $res['compare_primaria_ps5'] = $variante['compare_at_price'];
        } elseif ($cuenta == $config['idSecundaria'] && $consola == $config['idPs4']) {
            $res['precio_secundaria_ps4'] = $variante['price'];
            $res['compare_secundaria_ps4'] = $variante['compare_at_price'];
        } elseif ($cuenta == $config['idSecundaria'] && $consola == $config['idPs5']) {
            $res['precio_secundaria_ps5'] = $variante['price'];
            $res['compare_secundaria_ps5'] = $variante['compare_at_price'];
        }
    }

    return $res;
}

    public function quitarOferta($id, JumpsellerApiService $jumpseller)
    {
        $idCuenta = env('JUMPSELLER_CUENTA_OPTION_ID');
        $idPrimaria = env('JUMPSELLER_PRIMARIA_OPTION_VALUE_ID');
        $idSecundaria = env('JUMPSELLER_SECUNDARIA_OPTION_VALUE_ID');
        $idConsola = env('JUMPSELLER_CONSOLA_OPTION_ID');
        $idPs4 = env('JUMPSELLER_PS4_OPTION_VALUE_ID');
        $idPs5 = env('JUMPSELLER_PS5_OPTION_VALUE_ID');
        $ofertasCategoriaId = env('JUMPSELLER_OFERTAS_CATEGORY_ID');
        $producto = $jumpseller->getProductoId($id);

        $categorias = $producto['product']['categories'];
        $variantes = $producto['product']['variants'];
        $nombreJuego = $producto['product']['name'];

        // Filtra la categoría de ofertas
        $categoriasSinOfertas = array_filter($categorias, function ($categoria) use ($ofertasCategoriaId) {
            return $categoria['id'] != $ofertasCategoriaId;
        });

        // Convierte los IDs al formato requerido por la API
        $categoriasParaApi = array_map(function ($categoria) {
            return ['id' => $categoria['id']];
        }, $categoriasSinOfertas);

        $variantesParaApi = [];
        $entroSecundaria = false;
        $juegoExisteSecundariaPs4 = null;
        $juegoExisteSecundariaPs5 = null;
        $juegoExistePrimaria = null;

        foreach ($variantes as $variante) {
            $precioCompare = $variante['compare_at_price'] ?? null;
            $idVariante = $variante['id'] ?? null;
            $cuenta = null;
            $consola = null;

            foreach ($variante['options'] as $opcion) {
                if ($opcion['product_option_id'] == $idCuenta) {
                    $cuenta = $opcion['product_option_value_id'];
                }
                if ($opcion['product_option_id'] == $idConsola) {
                    $consola = $opcion['product_option_value_id'];
                }
            }

            // Primaria PS4
            if ($cuenta == $idPrimaria && $consola == $idPs4) {
                $juegoExistePrimaria = CorreoJuego::where('juego', $nombreJuego)
                    ->where('primaria_ps4', '<', 2)
                    ->where('disponible', true)
                    ->first();

                if ($juegoExistePrimaria) {
                    $variantesParaApi[] = ['id' => $idVariante];
                } else {
                    if ($precioCompare != null) {
                        $variantesParaApi[] = ['id' => $idVariante, 'price' => $precioCompare, 'compare_at_price' => null];
                    } else {
                        $variantesParaApi[] = ['id' => $idVariante];
                    }
                }
            }

            // Primaria PS5
            if ($cuenta == $idPrimaria && $consola == $idPs5) {
                $juegoExistePrimaria = CorreoJuego::where('juego', $nombreJuego)
                    ->where('primaria_ps5', '<', 2)
                    ->where('disponible', true)
                    ->first();

                if ($juegoExistePrimaria) {
                    $variantesParaApi[] = ['id' => $idVariante];
                } else {
                    if ($precioCompare != null) {
                        $variantesParaApi[] = ['id' => $idVariante, 'price' => $precioCompare, 'compare_at_price' => null];
                    } else {
                        $variantesParaApi[] = ['id' => $idVariante];
                    }
                }
            }

            // Secundaria PS4
            if ($cuenta == $idSecundaria && $consola == $idPs4) {
                $juegoExisteSecundariaPs4 = CorreoJuego::where('juego', $nombreJuego)
                    ->where('primaria_ps4', '>=', 2)
                    ->where('secundaria', '=', 0)
                    ->where('disponible', true)
                    ->first();

                $entroSecundaria = true;
                if ($juegoExisteSecundariaPs4) {
                    $variantesParaApi[] = ['id' => $idVariante];
                }
            }

            // Secundaria PS5
            if ($cuenta == $idSecundaria && $consola == $idPs5) {
                $juegoExisteSecundariaPs5 = CorreoJuego::where('juego', $nombreJuego)
                    ->where('primaria_ps5', '>=', 2)
                    ->where('secundaria', '=', 0)
                    ->where('disponible', true)
                    ->first();

                $entroSecundaria = true;
                if ($juegoExisteSecundariaPs5) {
                    $variantesParaApi[] = ['id' => $idVariante];
                }
            }
        }

        if ($entroSecundaria && !$juegoExisteSecundariaPs5 && !$juegoExisteSecundariaPs4) {
            $jumpseller->deleteProductoOptionValue($id, $idCuenta, $idSecundaria);
        }

        $data = [
            'product' => [
                'categories' => $categoriasParaApi,
                'variants' => $variantesParaApi
            ]
        ];

        // Actualiza el producto enviando solo los IDs
        $jumpseller->updateProducto($id, $data);
        $t_mensaje = "El producto '$nombreJuego' ha sido quitado de la categoría de ofertas.";
        $t_tipo = 'success';

        return redirect()->back()->with('mensaje', $t_mensaje)->with('tipo', $t_tipo);
    }

    public function quitarTodasOfertas(Request $request, JumpsellerApiService $jumpseller)
    {
        $ids = $request->input('ids', []);

        foreach ($ids as $id) {
            // Reutilizamos la lógica llamando al método existente
            // Nota: Como quitarOferta devuelve un redirect, aquí simplemente lo ejecutamos
            $this->quitarOferta($id, $jumpseller);
        }

        return redirect()->back()->with('mensaje', 'Se han procesado todas las ofertas.')->with('tipo', 'success');
    }

    public function sincronizarProductos(JumpsellerApiService $jumpseller){
        $numeroProductos = $jumpseller->getProudctoAllCount();

        if ($numeroProductos['count'] === 0) {
            return response()->json(['message' => 'No hay productos en oferta'], 404);
        }

        $total = $numeroProductos['count'];
        $porPagina = 100;
        $paginas = (int) ceil($total / $porPagina);

        $todosLosProductosJumpseller = [];

        for ($i = 1; $i <= $paginas; $i++) {
            $productos = $jumpseller->getProductoAll($porPagina, $i);
            if (empty($productos)) {
                continue;
            }

            foreach ($productos as $producto) {
                $todosLosProductosJumpseller[] = $producto['product']['name'];
            }
        }

        $todosLosJuegos = CorreoJuego::distinct()->pluck('juego')->toArray();

        $juegosConEstado = [];

        foreach ($todosLosJuegos as $juego) {
            if (!in_array($juego, $todosLosProductosJumpseller)) {
                $juegosConEstado[] = [
                    'nombre' => $juego,
                    'estado' => 'Pendiente de sincronización'
                ];
            }
        }

        sort($todosLosProductosJumpseller, SORT_NATURAL | SORT_FLAG_CASE);

        return Inertia::render('ProductosJumpseller/Sincronizar', [
            'juegos' => $juegosConEstado,
            'juegosJumpseller' => $todosLosProductosJumpseller,
            'mensaje' => session('mensaje'),
            'tipo' => session('tipo', 'success'),
        ]);
    }

    public function sincronizarProductosUpdate(Request $request){
        $request->validate([
            'juego' => 'required',
            'juego_jumpseller' => 'required',
        ]);

        $correos = CorreoJuego::where('juego', $request->juego)->get();

        foreach ($correos as $correo) {
            $correo->juego = $request->juego_jumpseller;
            $correo->save();
        }

        return redirect()->back()->with('mensaje', 'Juego sincronizado correctamente')->with('tipo', 'success');
    }
}