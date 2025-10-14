<?php

namespace App\Http\Controllers;

use App\Models\CorreoJuego;
use App\Models\Notificaciones;
use App\Services\JumpsellerApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductosJumpsellerController extends Controller
{
    public function index(JumpsellerApiService $jumpseller)
    {
        $numeroProductosOferta = $jumpseller->getProductosOfertaCount();

        if ($numeroProductosOferta['count'] === 0) {
            return response()->json(['message' => 'No hay productos en oferta'], 404);
        }

        $total = $numeroProductosOferta['count'];
        $porPagina = 100;
        $paginas = (int) ceil($total / $porPagina);

        $todosLosProductos = [];
        $idCuenta = env('JUMPSELLER_CUENTA_OPTION_ID');
        $idPrimaria = env('JUMPSELLER_PRIMARIA_OPTION_VALUE_ID');
        $idSecundaria = env('JUMPSELLER_SECUNDARIA_OPTION_VALUE_ID');
        $idConsola = env('JUMPSELLER_CONSOLA_OPTION_ID');
        $idPs4 = env('JUMPSELLER_PS4_OPTION_VALUE_ID');
        $idPs5 = env('JUMPSELLER_PS5_OPTION_VALUE_ID');
        
        for ($i = 1; $i <= $paginas; $i++) {
            $productos = $jumpseller->getProductosOferta($porPagina, $i);
            if (empty($productos)) {
                continue;
            }

            foreach ($productos as $producto) {
                $precioPrimariaPs4 = null;
                $precioPrimariaPs5 = null;
                $precioSecundariaPs4 = null;
                $precioSecundariaPs5 = null;
                $comparePrimariaPs4 = null;
                $comparePrimariaPs5 = null;
                $compareSecundariaPs4 = null;
                $compareSecundariaPs5 = null;

                foreach ($producto['product']['variants'] as $variante) {
                    $precio = $variante['price'] ?? null;
                    $precioCompare = $variante['compare_at_price'] ?? null;
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
                        $precioPrimariaPs4 = $precio;
                        $comparePrimariaPs4 = $precioCompare;
                    }
                    // Primaria PS5
                    if ($cuenta == $idPrimaria && $consola == $idPs5) {
                        $precioPrimariaPs5 = $precio;
                        $comparePrimariaPs5 = $precioCompare;
                    }
                    // Secundaria PS4
                    if ($cuenta == $idSecundaria && $consola == $idPs4) {
                        $precioSecundariaPs4 = $precio;
                        $compareSecundariaPs4 = $precioCompare;
                    }
                    // Secundaria PS5
                    if ($cuenta == $idSecundaria && $consola == $idPs5) {
                        $precioSecundariaPs5 = $precio;
                        $compareSecundariaPs5 = $precioCompare;
                    }
                }

                $todosLosProductos[] = [
                    'id' => $producto['product']['id'],
                    'nombre' => $producto['product']['name'],
                    'imagen' => $producto['product']['images'][0]['url'] ?? null,
                    'precio_primaria_ps4' => $precioPrimariaPs4,
                    'compare_primaria_ps4' => $comparePrimariaPs4,
                    'precio_primaria_ps5' => $precioPrimariaPs5,
                    'compare_primaria_ps5' => $comparePrimariaPs5,
                    'precio_secundaria_ps4' => $precioSecundariaPs4,
                    'compare_secundaria_ps4' => $compareSecundariaPs4,
                    'precio_secundaria_ps5' => $precioSecundariaPs5,
                    'compare_secundaria_ps5' => $compareSecundariaPs5,
                ];
            }
        }
        
        return Inertia::render('ProductosJumpseller/Ofertas', [
            'productos' => $todosLosProductos,
            'mensaje' => session('mensaje'),
            'tipo' => session('tipo', 'success'),
        ]);
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
        $correoJuego = CorreoJuego::where('juego', $nombreJuego)
            ->where('disponible', true)
            ->get();

        if ($correoJuego->isEmpty()) {
            Notificaciones::create([
                'tipo' => 'coincidencia_juego',
                'juego' => $nombreJuego,
                'mensaje' => "No se encontraron juegos que coincidan con el nombre: $nombreJuego",
            ]);
            $t_mensaje = "No se encontraron juegos que coincidan con el nombre: $nombreJuego";
            $t_tipo = 'error';
        } else {
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
        }

        return redirect()->back()->with('mensaje', $t_mensaje)->with('tipo', $t_tipo);
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