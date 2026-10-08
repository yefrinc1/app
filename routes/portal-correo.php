<?php

use App\Http\Controllers\PortalCorreoVerificacionController;
use Illuminate\Support\Facades\Route;

// Fuera del grupo del portal: estas rutas deben funcionar antes de verificar el correo.
Route::get('/mi-cuenta/verificar-correo', [PortalCorreoVerificacionController::class, 'notice'])
    ->name('portal.correo.notice');
Route::post('/mi-cuenta/verificar-correo/reenviar', [PortalCorreoVerificacionController::class, 'send'])
    ->middleware('throttle:1,1')->name('portal.correo.enviar');
Route::get('/mi-cuenta/verificar-correo/{id}/{hash}', [PortalCorreoVerificacionController::class, 'verify'])
    ->whereNumber('id')->middleware('throttle:10,1')->name('portal.correo.verificar');
