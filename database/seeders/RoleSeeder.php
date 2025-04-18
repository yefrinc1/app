<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $administrador = Role::create(['name' => 'Administrador']);
        $vendedor = Role::create(['name' => 'Vendedor']);

        Permission::create(['name' => 'menu-correos'])->syncRoles([$administrador]);
        Permission::create(['name' => 'codigo-verificacion.create'])->syncRoles([$administrador]);
        Permission::create(['name' => 'codigo-verificacion.index'])->syncRoles([$administrador]);
        Permission::create(['name' => 'movimientos.index'])->syncRoles([$administrador]);
        Permission::create(['name' => 'cierre-caja.index'])->syncRoles([$administrador]);
        Permission::create(['name' => 'pagos.create.pagar'])->syncRoles([$administrador]);
    }
}
