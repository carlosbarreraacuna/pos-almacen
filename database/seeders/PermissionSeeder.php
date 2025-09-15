<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
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
            [
                'name' => 'reports.view',
                'display_name' => 'Ver Reportes',
                'description' => 'Permite ver reportes del sistema'
            ],
            [
                'name' => 'admin.access',
                'display_name' => 'Acceso Administrativo',
                'description' => 'Permite acceso completo al sistema'
            ]
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
