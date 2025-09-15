<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear rol de Administrador
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrador',
                'description' => 'Acceso completo al sistema'
            ]
        );

        // Crear rol de Gerente
        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Gerente',
                'description' => 'Gestión de inventario y reportes'
            ]
        );

        // Crear rol de Empleado
        $employeeRole = Role::firstOrCreate(
            ['name' => 'employee'],
            [
                'display_name' => 'Empleado',
                'description' => 'Acceso básico al sistema'
            ]
        );

        // Asignar todos los permisos al administrador
        $allPermissions = Permission::all();
        $adminRole->permissions()->sync($allPermissions->pluck('id'));

        // Asignar permisos específicos al gerente
        $managerPermissions = Permission::whereIn('name', [
            'products.view',
            'products.create',
            'products.edit',
            'inventory.view',
            'inventory.manage',
            'reports.view',
            'users.view'
        ])->get();
        $managerRole->permissions()->sync($managerPermissions->pluck('id'));

        // Asignar permisos básicos al empleado
        $employeePermissions = Permission::whereIn('name', [
            'products.view',
            'inventory.view'
        ])->get();
        $employeeRole->permissions()->sync($employeePermissions->pluck('id'));
    }
}
