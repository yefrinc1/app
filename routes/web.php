<?php

use App\Http\Controllers\AdministracionAccesosController;
use App\Http\Controllers\AgregarUsuarioController;
use App\Http\Controllers\AnalisisDemandaController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\CodigoVerificacionController;
use App\Http\Controllers\CorreoCreacionCompletaController;
use App\Http\Controllers\CorreoGlobalesController;
use App\Http\Controllers\CorreoJuegoController;
use App\Http\Controllers\CorreoMadreController;
use App\Http\Controllers\CorreoPrincipalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\JuegoController;
use App\Http\Controllers\MovimientosController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PortalClienteController;
use App\Http\Controllers\PortalCodigoController;
use App\Http\Controllers\PortalGarantiaController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\ProductosJumpsellerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RotacionInventarioController;
use App\Http\Controllers\VentasController;
use App\Http\Middleware\EnsureClientePortal;
use App\Http\Middleware\EnsurePanelAdministrativo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if (! $request->user()) {
        return redirect()->route('login');
    }

    return redirect()->route($request->user()->hasRole('cliente') ? 'portal.index' : 'dashboard');
});

Route::prefix('mi-cuenta')->middleware(['auth', EnsureClientePortal::class])->group(function () {
    Route::get('/', [PortalClienteController::class, 'index'])->name('portal.index');
    Route::get('/pedidos', [PortalClienteController::class, 'pedidos'])->name('portal.pedidos.index');
    Route::get('/pedidos/{pedido}', [PortalClienteController::class, 'pedido'])
        ->whereNumber('pedido')
        ->name('portal.pedidos.show');
    Route::get('/juegos/{origen}/{id}', [PortalClienteController::class, 'show'])
        ->whereIn('origen', ['pedido', 'historica'])
        ->whereNumber('id')
        ->name('portal.juegos.show');
    Route::post('/juegos/{origen}/{id}/evidencias', [PortalGarantiaController::class, 'store'])
        ->whereIn('origen', ['pedido', 'historica'])->whereNumber('id')
        ->name('portal.garantias.store');
    Route::post('/juegos/{origen}/{id}/accesos', [PortalGarantiaController::class, 'acceso'])
        ->whereIn('origen', ['pedido', 'historica'])->whereNumber('id')
        ->name('portal.juegos.accesos.store');
    Route::post('/juegos/{origen}/{id}/codigo', [PortalCodigoController::class, 'store'])
        ->whereIn('origen', ['pedido', 'historica'])->whereNumber('id')
        ->middleware('throttle:8,1')
        ->name('portal.juegos.codigo');
    Route::get('/evidencias/{evidencia}/archivo', [PortalGarantiaController::class, 'archivo'])
        ->name('portal.garantias.archivo');
});

Route::middleware(['auth', EnsurePanelAdministrativo::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('/correo-principal', CorreoPrincipalController::class);
    Route::resource('/correo-madre', CorreoMadreController::class);
    Route::resource('/correo-globales', CorreoGlobalesController::class);
    Route::resource('/codigo-verificacion', CodigoVerificacionController::class);
    Route::get('/buscar-correos', [CodigoVerificacionController::class, 'buscar'])->name('buscar-correos');
    Route::get('/consultar-codigo', [CodigoVerificacionController::class, 'consultarCodigo'])->name('consultar-codigo');
    Route::get('/consultar-todos-codigos', [CodigoVerificacionController::class, 'consultarTodosCodigos'])->name('consultar-todos-codigos');
    Route::get('/codigo-verificacion-generar', [CodigoVerificacionController::class, 'generarCodigo'])->name('codigo-verificacion.generar');
    Route::get('/codigo-generar-disponible', [CodigoVerificacionController::class, 'generarCodigoDisponible'])->name('codigo-generar-disponible');
    Route::get('/correo-juegos/crear-juego-manual', [CorreoJuegoController::class, 'crearJuegoManual'])->name('correo-juegos.crear-juego-manual');
    Route::post('/correo-juegos/store-manual', [CorreoJuegoController::class, 'storeManual'])->name('correo-juegos.store-manual');
    Route::get('/correo-juegos/creacion-completa', [CorreoCreacionCompletaController::class, 'create'])->name('correo-juegos.creacion-completa');
    Route::get('/correo-juegos/creacion-completa/madres', [CorreoCreacionCompletaController::class, 'buscarMadres'])->name('correo-juegos.creacion-completa.madres');
    Route::post('/correo-juegos/creacion-completa', [CorreoCreacionCompletaController::class, 'store'])->name('correo-juegos.creacion-completa.store');
    Route::resource('/correo-juegos', CorreoJuegoController::class);
    Route::get('/ventas/comprobar-existencia-juego', [VentasController::class, 'comprobarExistenciaJuegoApi'])->name('ventas.comprobar-existencia-juego');
    Route::resource('/ventas', VentasController::class);
    Route::get('/buscar-juegos', [VentasController::class, 'buscarJuego'])->name('buscar-juegos');
    Route::resource('/notificaciones', NotificacionesController::class);
    Route::resource('/movimientos', MovimientosController::class);
    Route::resource('/juegos', JuegoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('/presupuestos', PresupuestoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/buscar-juegos-catalogo', [JuegoController::class, 'buscar'])->name('buscar-juegos-catalogo');
    Route::resource('/cierre-caja', CierreCajaController::class);
    Route::post('/correo-madre/{id}/usar-como-hijo', [CorreoMadreController::class, 'usarComoHijo'])->name('correo-madre.usar-como-hijo');
    Route::get('/correo-madre/quitar-hijo/{id}', [CorreoMadreController::class, 'quitarHijo'])->name('correo-madre.quitarHijo');
    Route::resource('/pagos', PagoController::class);
    Route::resource('/agregar-usuario', AgregarUsuarioController::class)->only(['create', 'store']);
    Route::prefix('administracion/accesos')->name('administracion.accesos.')->group(function () {
        Route::get('/', [AdministracionAccesosController::class, 'index'])->name('index');
        Route::post('/permisos', [AdministracionAccesosController::class, 'storePermission'])->name('permisos.store');
        Route::patch('/permisos/{permission}', [AdministracionAccesosController::class, 'updatePermission'])->name('permisos.update');
        Route::delete('/permisos/{permission}', [AdministracionAccesosController::class, 'destroyPermission'])->name('permisos.destroy');
        Route::post('/roles', [AdministracionAccesosController::class, 'storeRole'])->name('roles.store');
        Route::patch('/roles/{role}', [AdministracionAccesosController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [AdministracionAccesosController::class, 'destroyRole'])->name('roles.destroy');
        Route::put('/roles/{role}/permisos', [AdministracionAccesosController::class, 'syncRolePermissions'])->name('roles.permisos');
        Route::post('/usuarios', [AdministracionAccesosController::class, 'storeUser'])->name('usuarios.store');
        Route::put('/usuarios/{user}', [AdministracionAccesosController::class, 'syncUserAccess'])->name('usuarios.update');
    });
    Route::get('/consultar-inventario', [CorreoJuegoController::class, 'consultarInventario'])->middleware('can:inventario.consultar')->name('consultar-inventario');
    Route::get('/inventario/analisis-demanda', [AnalisisDemandaController::class, 'index'])->name('inventario.analisis.index');
    Route::get('/inventario/analisis-demanda/exportar', [AnalisisDemandaController::class, 'exportar'])->name('inventario.analisis.exportar');
    Route::get('/inventario/analisis-demanda/{juego}', [AnalisisDemandaController::class, 'show'])->name('inventario.analisis.show');
    Route::get('/inventario/rotacion', [RotacionInventarioController::class, 'index'])->name('inventario.rotacion.index');
    Route::get('/inventario/rotacion/exportar', [RotacionInventarioController::class, 'exportar'])->name('inventario.rotacion.exportar');
    Route::get('/estadistica-juegos', [EstadisticaController::class, 'estadisticaJuegos'])->name('estadistica-juegos');
    Route::get('/resumen-mensual', [EstadisticaController::class, 'resumenMensual'])->name('resumen-mensual');
    Route::get('/productos-oferta-jumpseller', [ProductosJumpsellerController::class, 'index'])->name('productos-oferta-jumpseller');
    Route::get('/productos-oferta-jumpseller/exportar', [ProductosJumpsellerController::class, 'exportarOfertas'])->name('productos-oferta-jumpseller.exportar');
    Route::delete('/productos-oferta-jumpseller/{id}/quitar-oferta', [ProductosJumpsellerController::class, 'quitarOferta'])->name('productos-oferta-jumpseller.quitar-oferta');
    Route::post('/productos-oferta-jumpseller/quitar-todas', [ProductosJumpsellerController::class, 'quitarTodasOfertas'])->name('productos-oferta-jumpseller.quitar-todas');
    Route::get('/productos-sincronizar', [ProductosJumpsellerController::class, 'sincronizarProductos'])->name('productos-sincronizar');
    Route::patch('/productos-sincronizar', [ProductosJumpsellerController::class, 'sincronizarProductosUpdate'])->name('productos-sincronizar.update');

    require __DIR__.'/pedidos.php';
});

Route::get('/cierre-caja-automatico', [CierreCajaController::class, 'cierreCajaAutomatico'])->name('cierre-caja-automatico');

require __DIR__.'/portal-correo.php';
require __DIR__.'/auth.php';
