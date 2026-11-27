<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisosAdministrador = [
            // Dashboard
            'dashboard.totales.ver',

            // Pedidos
            'pedidos.ver',
            'pedidos.crear',
            'pedidos.editar',
            'pedidos.cancelar',
            'pedidos.eliminar',
            'pagos.subir',
            'pagos.revisar',
            'pedidos.entregar',
            'pedidos.anular',
            'reembolsos.crear',
            'reembolsos.revisar',
            'garantias.revisar',

            // Clientes
            'clientes.ver',
            'clientes.editar',

            // Ventas
            'ventas.ver',
            'ventas.crear',
            'ventas.editar',
            'ventas.eliminar',

            // Inventario
            'inventario.consultar',
            'inventario.analisis.ver',
            'inventario.datos.completos',
            'inventario.titulos',

            // Cuentas
            'correos.principales',
            'correos.madre',
            'correos.globales',
            'correos.juegos',
            'correos.juegos.manual',

            // Codigos
            'codigos.consultar',
            'codigos.crear',
            'codigos.generar',

            // Finanzas
            'finanzas.movimientos',
            'finanzas.ver.pago',
            'finanzas.registrar.pago',
            'finanzas.cerrar.caja',
            'finanzas.cerrar.caja.ver',
            'finanzas.presupuesto',

            // Reportes
            'reportes.juegos',
            'reportes.mensual',

            // Integraciones
            'integraciones.productos.ofertas',
            'integraciones.productos.sincronizar',

            // Usuarios
            'usuarios.crear',
            'usuarios.administrar',
        ];

        $permisosAsesor = [
            // Pedidos
            'pedidos.ver',
            'pedidos.crear',
            'pedidos.editar',
            'pagos.subir',
            'pedidos.entregar',
            'reembolsos.crear',
            'garantias.revisar',

            // Clientes
            'clientes.ver',
            'clientes.editar',

            // Ventas
            'ventas.ver',
            'ventas.crear',

            // Inventario
            'inventario.consultar',

            // Codigos
            'codigos.crear',
            'codigos.generar',

            // Finanzas
            'finanzas.ver.pago',
        ];

        $todosLosPermisos = array_unique([
            ...$permisosAdministrador,
            ...$permisosAsesor,
        ]);

        foreach ($todosLosPermisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        $administrador = Role::query()->where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', ['administrador'])->first()
            ?? Role::create(['name' => 'administrador', 'guard_name' => 'web']);

        $asesor = Role::query()->where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', ['asesor'])->first()
            ?? Role::create(['name' => 'asesor', 'guard_name' => 'web']);

        $administrador->syncPermissions($permisosAdministrador);
        $asesor->syncPermissions($permisosAsesor);

        $cliente = Role::query()->where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', ['cliente'])->first()
            ?? Role::create(['name' => 'cliente', 'guard_name' => 'web']);

        $cliente->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
