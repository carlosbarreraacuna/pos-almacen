<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class SalesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Crear permisos del módulo de ventas
        $salesPermissions = [
            // Permisos básicos de ventas
            'sales.view' => 'Ver ventas',
            'sales.create' => 'Crear ventas',
            'sales.edit' => 'Editar ventas',
            'sales.delete' => 'Eliminar ventas',
            'sales.complete' => 'Completar ventas',
            'sales.cancel' => 'Cancelar ventas',
            'sales.reports' => 'Ver reportes de ventas',
            
            // Permisos de plantillas de venta
            'sale_templates.view' => 'Ver plantillas de venta',
            'sale_templates.create' => 'Crear plantillas de venta',
            'sale_templates.edit' => 'Editar plantillas de venta',
            'sale_templates.delete' => 'Eliminar plantillas de venta',
            'sale_templates.use' => 'Usar plantillas de venta',
            
            // Permisos de facturación electrónica
            'sales.electronic_invoice' => 'Generar facturas electrónicas',
            'electronic_invoices.view' => 'Ver facturas electrónicas',
            'electronic_invoices.create' => 'Crear facturas electrónicas',
            'electronic_invoices.edit' => 'Editar facturas electrónicas',
            'electronic_invoices.delete' => 'Eliminar facturas electrónicas',
            'electronic_invoices.send_dian' => 'Enviar facturas a DIAN',
            'electronic_invoices.generate_pdf' => 'Generar PDF de facturas',
            'electronic_invoices.generate_xml' => 'Generar XML de facturas',
            
            // Permisos de historial de ventas
            'sale_history.view' => 'Ver historial de ventas',
            'sale_history.export' => 'Exportar historial de ventas',
            'sale_history.analytics' => 'Ver análisis de ventas',
            'sale_history.recommendations' => 'Ver recomendaciones de ventas',
            
            // Permisos de pagos
            'payments.view' => 'Ver pagos',
            'payments.create' => 'Crear pagos',
            'payments.edit' => 'Editar pagos',
            'payments.delete' => 'Eliminar pagos',
            'payments.complete' => 'Completar pagos',
            'payments.cancel' => 'Cancelar pagos',
            'payments.reports' => 'Ver reportes de pagos'
        ];

        // Crear los permisos
        foreach ($salesPermissions as $permission => $description) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['display_name' => $description, 'description' => $description]
            );
        }

        // Crear roles específicos para ventas
        $salesRoles = [
            'vendedor' => [
                'description' => 'Vendedor - Puede realizar ventas básicas',
                'permissions' => [
                    'sales.view',
                    'sales.create',
                    'sale_templates.view',
                    'sale_templates.use',
                    'payments.view',
                    'payments.create'
                ]
            ],
            'supervisor_ventas' => [
                'description' => 'Supervisor de Ventas - Puede gestionar ventas y plantillas',
                'permissions' => [
                    'sales.view',
                    'sales.create',
                    'sales.edit',
                    'sales.complete',
                    'sales.cancel',
                    'sales.reports',
                    'sale_templates.view',
                    'sale_templates.create',
                    'sale_templates.edit',
                    'sale_templates.use',
                    'sale_history.view',
                    'sale_history.analytics',
                    'payments.view',
                    'payments.create',
                    'payments.edit',
                    'payments.complete'
                ]
            ],
            'gerente_ventas' => [
                'description' => 'Gerente de Ventas - Acceso completo al módulo de ventas',
                'permissions' => array_keys($salesPermissions)
            ],
            'facturador' => [
                'description' => 'Facturador - Especializado en facturación electrónica',
                'permissions' => [
                    'sales.view',
                    'sales.electronic_invoice',
                    'electronic_invoices.view',
                    'electronic_invoices.create',
                    'electronic_invoices.edit',
                    'electronic_invoices.send_dian',
                    'electronic_invoices.generate_pdf',
                    'electronic_invoices.generate_xml',
                    'payments.view'
                ]
            ]
        ];

        // Crear los roles y asignar permisos
        foreach ($salesRoles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['display_name' => $roleData['description'], 'description' => $roleData['description']]
            );

            // Asignar permisos al rol
            $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
            $role->permissions()->sync($permissions->pluck('id'));
        }

        $this->command->info('Permisos y roles de ventas creados exitosamente.');
    }
}
