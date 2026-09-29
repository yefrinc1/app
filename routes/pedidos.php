<?php

use App\Http\Controllers\ClientePedidoController;
use App\Http\Controllers\PagoLiquidacionController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoAnulacionController;
use App\Http\Controllers\PedidoAnulacionResolucionController;
use App\Http\Controllers\PedidoEntregaController;
use App\Http\Controllers\PedidoPagoController;
use App\Http\Controllers\PedidoReembolsoController;
use Illuminate\Support\Facades\Route;

Route::get('/clientes/buscar', [ClientePedidoController::class, 'buscar'])->name('clientes.buscar');
Route::post('/clientes/rapido', [ClientePedidoController::class, 'store'])->name('clientes.rapido.store');

Route::get('/pedidos/pagos/pendientes', [PedidoPagoController::class, 'pendientes'])->name('pedidos.pagos.pendientes');
Route::get('/pedidos/pagos/{pago}/comprobante', [PedidoPagoController::class, 'comprobante'])->name('pedidos.pagos.comprobante');
Route::post('/pedidos/{pedido}/pagos', [PedidoPagoController::class, 'store'])->name('pedidos.pagos.store');
Route::patch('/pedidos/pagos/{pago}/aprobar', [PedidoPagoController::class, 'aprobar'])->name('pedidos.pagos.aprobar');
Route::patch('/pedidos/pagos/{pago}/rechazar', [PedidoPagoController::class, 'rechazar'])->name('pedidos.pagos.rechazar');

Route::post('/pedidos/pagos/{pago}/liquidaciones', [PagoLiquidacionController::class, 'store'])->name('pedidos.pagos.liquidaciones.store');
Route::get('/pedidos/pagos/{pago}/liquidaciones/{liquidacion}/comprobante', [PagoLiquidacionController::class, 'comprobante'])->name('pedidos.pagos.liquidaciones.comprobante');

Route::post('/pedidos/{pedido}/generar-entregas', [PedidoEntregaController::class, 'generar'])->name('pedidos.generar-entregas');
Route::patch('/pedidos/entregas/{entrega}/confirmar', [PedidoEntregaController::class, 'confirmar'])->name('pedidos.entregas.confirmar');
Route::patch('/pedidos/{pedido}/completar', [PedidoEntregaController::class, 'completar'])->name('pedidos.completar');

Route::post('/pedidos/entregas/{entrega}/anular', [PedidoAnulacionController::class, 'store'])->name('pedidos.entregas.anular');
Route::post('/pedidos/entregas/{entrega}/resolver/reemplazo', [PedidoAnulacionResolucionController::class, 'reemplazar'])->name('pedidos.entregas.resolver.reemplazo');
Route::post('/pedidos/{pedido}/reembolsos', [PedidoReembolsoController::class, 'store'])->name('pedidos.reembolsos.store');
Route::get('/pedidos/reembolsos/{reembolso}/comprobante', [PedidoReembolsoController::class, 'comprobante'])->name('pedidos.reembolsos.comprobante');
Route::patch('/pedidos/reembolsos/{reembolso}/aprobar', [PedidoReembolsoController::class, 'aprobar'])->name('pedidos.reembolsos.aprobar');
Route::patch('/pedidos/reembolsos/{reembolso}/rechazar', [PedidoReembolsoController::class, 'rechazar'])->name('pedidos.reembolsos.rechazar');
Route::patch('/pedidos/reembolsos/{reembolso}/relacionar-detalle', [PedidoReembolsoController::class, 'relacionarDetalle'])->name('pedidos.reembolsos.relacionar-detalle');

Route::patch('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->name('pedidos.cancelar');
Route::resource('/pedidos', PedidoController::class)->only(['index', 'create', 'store', 'show']);
