<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'code' => 'SUP001',
                'name' => 'TechAdvanced',
                'business_name' => 'Tecnología Avanzada S.A.',
                'tax_id' => '20123456789',
                'type' => 'manufacturer',
                'contact_person' => 'Carlos Mendoza',
                'email' => 'ventas@techadvanced.com',
                'phone' => '+51-1-234-5678',
                'website' => 'https://techadvanced.com',
                'address' => 'Av. Tecnología 123',
                'city' => 'Lima',
                'state' => 'Lima',
                'postal_code' => '15001',
                'country' => 'Perú',
                'payment_terms_days' => 30,
                'credit_limit' => 50000.00,
                'currency' => 'USD',
                'lead_time_days' => 15,
                'minimum_order_amount' => 1000.00,
                'discount_percentage' => 5.00,
                'payment_method' => 'transfer',
                'is_active' => true,
                'rating' => 5,
                'notes' => 'Proveedor principal de equipos tecnológicos'
            ],
            [
                'code' => 'SUP002',
                'name' => 'DistriNacional',
                'business_name' => 'Distribuidora Nacional E.I.R.L.',
                'tax_id' => '20987654321',
                'type' => 'distributor',
                'contact_person' => 'Ana García',
                'email' => 'compras@distrinacional.com',
                'phone' => '+51-1-345-6789',
                'website' => 'https://distrinacional.com',
                'address' => 'Jr. Comercio 456',
                'city' => 'Arequipa',
                'state' => 'Arequipa',
                'postal_code' => '04001',
                'country' => 'Perú',
                'payment_terms_days' => 45,
                'credit_limit' => 30000.00,
                'currency' => 'USD',
                'lead_time_days' => 10,
                'minimum_order_amount' => 500.00,
                'discount_percentage' => 3.00,
                'payment_method' => 'credit',
                'is_active' => true,
                'rating' => 4,
                'notes' => 'Distribuidor de productos varios'
            ],
            [
                'code' => 'SUP003',
                'name' => 'LogiSur',
                'business_name' => 'Servicios Logísticos del Sur S.A.C.',
                'tax_id' => '20456789123',
                'type' => 'service_provider',
                'contact_person' => 'Roberto Silva',
                'email' => 'contacto@logisur.com',
                'phone' => '+51-54-567-890',
                'website' => 'https://logisur.com',
                'address' => 'Av. Industrial 789',
                'city' => 'Cusco',
                'state' => 'Cusco',
                'postal_code' => '08001',
                'country' => 'Perú',
                'payment_terms_days' => 15,
                'credit_limit' => 15000.00,
                'currency' => 'USD',
                'lead_time_days' => 5,
                'minimum_order_amount' => 200.00,
                'discount_percentage' => 2.00,
                'payment_method' => 'cash',
                'is_active' => true,
                'rating' => 4,
                'notes' => 'Proveedor de servicios logísticos'
            ],
            [
                'code' => 'SUP004',
                'name' => 'MetalNorte',
                'business_name' => 'Industrias Metálicas del Norte S.A.',
                'tax_id' => '20789123456',
                'type' => 'manufacturer',
                'contact_person' => 'Luis Fernández',
                'email' => 'ventas@metalnorte.com',
                'phone' => '+51-44-678-901',
                'website' => 'https://metalnorte.com',
                'address' => 'Parque Industrial Km 5',
                'city' => 'Trujillo',
                'state' => 'La Libertad',
                'postal_code' => '13001',
                'country' => 'Perú',
                'payment_terms_days' => 60,
                'credit_limit' => 75000.00,
                'currency' => 'USD',
                'lead_time_days' => 20,
                'minimum_order_amount' => 2000.00,
                'discount_percentage' => 7.00,
                'payment_method' => 'transfer',
                'is_active' => true,
                'rating' => 4,
                'notes' => 'Fabricante de productos metálicos industriales'
            ],
            [
                'code' => 'SUP005',
                'name' => 'GlobalImports',
                'business_name' => 'Global Imports LLC',
                'tax_id' => '98765432101',
                'type' => 'wholesaler',
                'contact_person' => 'Jennifer Smith',
                'email' => 'sales@globalimports.com',
                'phone' => '+1-555-123-4567',
                'website' => 'https://globalimports.com',
                'address' => '123 International Blvd',
                'city' => 'Miami',
                'state' => 'Florida',
                'postal_code' => '33101',
                'country' => 'Estados Unidos',
                'payment_terms_days' => 30,
                'credit_limit' => 100000.00,
                'currency' => 'USD',
                'lead_time_days' => 45,
                'minimum_order_amount' => 5000.00,
                'discount_percentage' => 10.00,
                'payment_method' => 'transfer',
                'is_active' => true,
                'rating' => 5,
                'notes' => 'Proveedor internacional de equipos especializados'
            ]
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
