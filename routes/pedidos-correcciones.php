<?php

use App\Http\Controllers\PedidoDetalleCorreccionController;
use Illuminate\Support\Facades\Route;

Route::get('/pedidos/{pedido}/detalles/{detalle}/corregir', [PedidoDetalleCorreccionController::class, 'edit'])
    ->name('pedidos.detalles.corregir.edit');
Route::patch('/pedidos/{pedido}/detalles/{detalle}/corregir', [PedidoDetalleCorreccionController::class, 'update'])
    ->name('pedidos.detalles.corregir.update');
