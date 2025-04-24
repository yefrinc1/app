<?php

use App\Http\Controllers\AgregarUsuarioController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\CodigoVerificacionController;
use App\Http\Controllers\CorreoGlobalesController;
use App\Http\Controllers\CorreoJuegoController;
use App\Http\Controllers\CorreoMadreController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CorreoPrincipalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovimientosController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\VentasController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified']);

Route::get('/dashboard', [DashboardController::class, 'index'] )->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
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
    Route::resource('/correo-juegos', CorreoJuegoController::class);
    Route::resource('/ventas', VentasController::class);
    Route::get('/buscar-juegos', [VentasController::class, 'buscarJuego'])->name('buscar-juegos');
    Route::resource('/notificaciones', NotificacionesController::class);
    Route::resource('/movimientos', MovimientosController::class);
    Route::resource('/cierre-caja', CierreCajaController::class);
    Route::get('/correo-madre/quitar-hijo/{id}', [CorreoMadreController::class, 'quitarHijo'])->name('correo-madre.quitarHijo');
    Route::resource('/pagos', PagoController::class);
    Route::resource('/agregar-usuario', AgregarUsuarioController::class)->only(['create', 'store']);;
});

require __DIR__.'/auth.php';
