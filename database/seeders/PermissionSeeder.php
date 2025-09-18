<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Permisos de usuarios
            [
                'name' => 'users.view',
                'display_name' => 'Ver Usuarios',
                'description' => 'Permite ver la lista de usuarios'
            ],
            [
                'name' => 'users.create',
                'display_name' => 'Crear Usuarios',
                'description' => 'Permite crear nuevos usuarios'
            ],
            [
                'name' => 'users.edit',
                'display_name' => 'Editar Usuarios',
                'description' => 'Permite editar usuarios existentes'
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Eliminar Usuarios',
                'description' => 'Permite eliminar usuarios'
            ],

            // Permisos de productos
            [
                'name' => 'products.view',
                'display_name' => 'Ver Productos',
                'description' => 'Permite ver la lista de productos'
            ],
            [
                'name' => 'products.create',
                'display_name' => 'Crear Productos',
                'description' => 'Permite crear nuevos productos'
            ],
            [
                'name' => 'products.edit',
                'display_name' => 'Editar Productos',
                'description' => 'Permite editar productos existentes'
            ],
            [
                'name' => 'products.delete',
                'display_name' => 'Eliminar Productos',
                'description' => 'Permite eliminar productos'
            ],

            // Permisos de inventario
            [
                'name' => 'inventory.view',
                'display_name' => 'Ver Inventario',
                'description' => 'Permite ver el inventario'
            ],
            [
                'name' => 'inventory.manage',
                'display_name' => 'Gestionar Inventario',
                'description' => 'Permite gestionar el inventario'
            ],

            // Permisos existentes
            [
                'name' => 'reports.view',
                'display_name' => 'Ver Reportes',
                'description' => 'Permite ver reportes del sistema'
            ],
            [
                'name' => 'admin.access',
                'display_name' => 'Acceso Administrativo',
                'description' => 'Permite acceso completo al sistema'
            ],

            // *** Nuevos permisos según tu menú ***
            [
                'name' => 'warehouse.view',
                'display_name' => 'Ver Almacenes',
                'description' => 'Permite ver la gestión de almacenes'
            ],
            [
                'name' => 'sales.create',
                'display_name' => 'Crear Ventas',
                'description' => 'Permite realizar ventas (Punto de Venta y Venta Directa)'
            ],
            [
                'name' => 'sales.view',
                'display_name' => 'Ver Ventas',
                'description' => 'Permite ver el historial de ventas'
            ],
            [
                'name' => 'suppliers.view',
                'display_name' => 'Ver Proveedores',
                'description' => 'Permite ver la lista de proveedores'
            ],
            [
                'name' => 'purchases.view',
                'display_name' => 'Ver Órdenes de Compra',
                'description' => 'Permite ver las órdenes de compra'
            ],
            [
                'name' => 'customers.view',
                'display_name' => 'Ver Clientes',
                'description' => 'Permite ver la base de datos de clientes'
            ],
            [
                'name' => 'analytics.view',
                'display_name' => 'Ver Análisis',
                'description' => 'Permite acceder a análisis avanzados'
            ],
            [
                'name' => 'roles.view',
                'display_name' => 'Ver Roles y Permisos',
                'description' => 'Permite gestionar roles y permisos'
            ],
            [
                'name' => 'settings.view',
                'display_name' => 'Ver Configuración',
                'description' => 'Permite acceder a la configuración del sistema'
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
