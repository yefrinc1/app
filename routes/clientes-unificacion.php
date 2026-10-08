<?php

use App\Http\Controllers\ClienteUnificacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('clientes/unificar')->name('clientes.unificar.')->group(function () {
    Route::get('/', [ClienteUnificacionController::class, 'index'])->name('index');
    Route::get('/buscar', [ClienteUnificacionController::class, 'buscar'])->name('buscar');
    Route::get('/previa', [ClienteUnificacionController::class, 'previa'])->name('previa');
    Route::post('/', [ClienteUnificacionController::class, 'store'])->name('store');
});
