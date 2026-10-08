<?php

use App\Http\Controllers\PedidoPagoEdicionController;
use Illuminate\Support\Facades\Route;

Route::get('/pedidos/pagos/{pago}/editar', [PedidoPagoEdicionController::class, 'edit'])
    ->name('pedidos.pagos.editar');
Route::patch('/pedidos/pagos/{pago}/editar', [PedidoPagoEdicionController::class, 'update'])
    ->name('pedidos.pagos.actualizar');
