<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class AllModulesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Permisos para todos los módulos del sidebar
        $allPermissions = [
            // Módulo Dashboard
            'dashboard.view' => 'Ver dashboard',
            
            // Módulo Gestión
            'users.view' => 'Ver usuarios',
            'users.create' => 'Crear usuarios',
            'users.edit' => 'Editar usuarios',
            'users.delete' => 'Eliminar usuarios',
            'roles.view' => 'Ver roles',
            'roles.create' => 'Crear roles',
            'roles.edit' => 'Editar roles',
            'roles.delete' => 'Eliminar roles',
            'products.view' => 'Ver productos',
            'products.create' => 'Crear productos',
            'products.edit' => 'Editar productos',
            'products.delete' => 'Eliminar productos',
            
            // Módulo Ventas
            'sales.view' => 'Ver ventas',
            'sales.create' => 'Crear ventas',
            'sales.edit' => 'Editar ventas',
            'sales.delete' => 'Eliminar ventas',
            'direct_sales.view' => 'Ver venta directa',
            'direct_sales.create' => 'Crear venta directa',
            'sale_templates.view' => 'Ver plantillas de venta',
            'sale_templates.create' => 'Crear plantillas de venta',
            'sale_history.view' => 'Ver historial de ventas',
            
            // Módulo Compras
            'purchases.view' => 'Ver compras',
            'purchases.create' => 'Crear compras',
            'purchases.edit' => 'Editar compras',
            'purchases.delete' => 'Eliminar compras',
            'suppliers.view' => 'Ver proveedores',
            'suppliers.create' => 'Crear proveedores',
            'suppliers.edit' => 'Editar proveedores',
            'suppliers.delete' => 'Eliminar proveedores',
            'purchase_orders.view' => 'Ver órdenes de compra',
            'purchase_orders.create' => 'Crear órdenes de compra',
            'purchase_orders.edit' => 'Editar órdenes de compra',
            'purchase_orders.delete' => 'Eliminar órdenes de compra',
            
            // Módulo CRM
            'customers.view' => 'Ver clientes',
            'customers.create' => 'Crear clientes',
            'customers.edit' => 'Editar clientes',
            'customers.delete' => 'Eliminar clientes',
            'leads.view' => 'Ver prospectos',
            'leads.create' => 'Crear prospectos',
            'leads.edit' => 'Editar prospectos',
            'leads.delete' => 'Eliminar prospectos',
            'campaigns.view' => 'Ver campañas',
            'campaigns.create' => 'Crear campañas',
            'campaigns.edit' => 'Editar campañas',
            'campaigns.delete' => 'Eliminar campañas',
            
            // Módulo Análisis
            'analytics.view' => 'Ver análisis',
            'reports.view' => 'Ver reportes',
            'reports.create' => 'Crear reportes',
            'reports.export' => 'Exportar reportes',
            'kpis.view' => 'Ver KPIs',
            'forecasting.view' => 'Ver pronósticos',
            
            // Módulo Sistema
            'system.view' => 'Ver configuración del sistema',
            'system.edit' => 'Editar configuración del sistema',
            'backups.view' => 'Ver respaldos',
            'backups.create' => 'Crear respaldos',
            'backups.restore' => 'Restaurar respaldos',
            'logs.view' => 'Ver logs del sistema',
            'maintenance.access' => 'Acceso a mantenimiento',
            'integrations.view' => 'Ver integraciones',
            'integrations.manage' => 'Gestionar integraciones',
            
            // Permisos generales
            'admin.access' => 'Acceso administrativo completo',
            'inventory.view' => 'Ver inventario',
            'inventory.manage' => 'Gestionar inventario'
        ];

        // Crear todos los permisos
        foreach ($allPermissions as $permission => $description) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['display_name' => $description, 'description' => $description]
            );
        }

        // Asignar todos los permisos al rol de administrador
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $allPermissionIds = Permission::all()->pluck('id');
            $adminRole->permissions()->sync($allPermissionIds);
            $this->command->info('Todos los permisos asignados al rol de administrador.');
        }

        $this->command->info('Permisos de todos los módulos creados exitosamente.');
    }
}