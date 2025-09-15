<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\Location;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Almacén Principal
        $mainWarehouse = Warehouse::create([
            'code' => 'WH001',
            'name' => 'Almacén Central',
            'description' => 'Almacén principal para productos generales',
            'type' => 'main',
            'is_main' => true,
            'is_active' => true,
            'manager_name' => 'Juan Pérez',
            'phone' => '+1-555-0101',
            'email' => 'central@almacen.com',
            'address' => 'Av. Industrial 123',
            'city' => 'Ciudad de México',
            'state' => 'CDMX',
            'postal_code' => '01234',
            'country' => 'México',
            'total_capacity' => 10000.00,
            'used_capacity' => 3500.00,
            'capacity_unit' => 'm3',
            'temperature_controlled' => true,
            'min_temperature' => 2.0,
            'max_temperature' => 8.0,
            'temperature_unit' => 'C',
            'security_level' => 'high',
            'storage_cost_per_unit' => 15.50,
            'handling_cost_per_unit' => 2.75,
            'operating_hours' => [
                'monday' => ['open' => '08:00', 'close' => '18:00'],
                'tuesday' => ['open' => '08:00', 'close' => '18:00'],
                'wednesday' => ['open' => '08:00', 'close' => '18:00'],
                'thursday' => ['open' => '08:00', 'close' => '18:00'],
                'friday' => ['open' => '08:00', 'close' => '18:00'],
                'saturday' => ['open' => '09:00', 'close' => '14:00'],
                'sunday' => ['open' => null, 'close' => null]
            ],
            'notes' => 'Almacén principal con control de temperatura para productos perecederos',
            'metadata' => [
                'certifications' => ['ISO 9001', 'HACCP'],
                'equipment' => ['montacargas', 'sistema_wms', 'cámaras_frigoríficas'],
                'dock_doors' => 8,
                'loading_bays' => 4,
                'office_space' => true
            ]
        ]);

        // Almacén Secundario
        $secondaryWarehouse = Warehouse::create([
            'code' => 'WH002',
            'name' => 'Almacén Norte',
            'description' => 'Almacén regional para la zona norte',
            'type' => 'regional',
            'is_main' => false,
            'is_active' => true,
            'manager_name' => 'María González',
            'phone' => '+1-555-0102',
            'email' => 'norte@almacen.com',
            'address' => 'Calle Norte 456',
            'city' => 'Monterrey',
            'state' => 'Nuevo León',
            'postal_code' => '64000',
            'country' => 'México',
            'total_capacity' => 5000.00,
            'used_capacity' => 1200.00,
            'capacity_unit' => 'm3',
            'temperature_controlled' => false,
            'security_level' => 'medium',
            'storage_cost_per_unit' => 12.00,
            'handling_cost_per_unit' => 2.00,
            'operating_hours' => [
                'monday' => ['open' => '07:00', 'close' => '17:00'],
                'tuesday' => ['open' => '07:00', 'close' => '17:00'],
                'wednesday' => ['open' => '07:00', 'close' => '17:00'],
                'thursday' => ['open' => '07:00', 'close' => '17:00'],
                'friday' => ['open' => '07:00', 'close' => '17:00'],
                'saturday' => ['open' => null, 'close' => null],
                'sunday' => ['open' => null, 'close' => null]
            ],
            'notes' => 'Almacén regional para distribución en el norte del país',
            'metadata' => [
                'equipment' => ['montacargas', 'sistema_básico'],
                'dock_doors' => 4,
                'loading_bays' => 2,
                'office_space' => false
            ]
        ]);

        // Almacén de Tránsito
        $transitWarehouse = Warehouse::create([
            'code' => 'WH003',
            'name' => 'Centro de Distribución',
            'description' => 'Centro de distribución para tránsito rápido',
            'type' => 'distribution',
            'is_main' => false,
            'is_active' => true,
            'manager_name' => 'Carlos Rodríguez',
            'phone' => '+1-555-0103',
            'email' => 'transito@almacen.com',
            'address' => 'Zona Industrial 789',
            'city' => 'Guadalajara',
            'state' => 'Jalisco',
            'postal_code' => '44100',
            'country' => 'México',
            'total_capacity' => 3000.00,
            'used_capacity' => 800.00,
            'capacity_unit' => 'm3',
            'temperature_controlled' => false,
            'security_level' => 'medium',
            'storage_cost_per_unit' => 8.00,
            'handling_cost_per_unit' => 3.50,
            'operating_hours' => [
                'monday' => ['open' => '06:00', 'close' => '22:00'],
                'tuesday' => ['open' => '06:00', 'close' => '22:00'],
                'wednesday' => ['open' => '06:00', 'close' => '22:00'],
                'thursday' => ['open' => '06:00', 'close' => '22:00'],
                'friday' => ['open' => '06:00', 'close' => '22:00'],
                'saturday' => ['open' => '08:00', 'close' => '16:00'],
                'sunday' => ['open' => null, 'close' => null]
            ],
            'notes' => 'Centro de distribución para tránsito rápido de mercancías',
            'metadata' => [
                'equipment' => ['bandas_transportadoras', 'sistema_sorting'],
                'dock_doors' => 2,
                'loading_bays' => 1,
                'office_space' => false
            ]
        ]);

        // Almacén de Cuarentena
        $quarantineWarehouse = Warehouse::create([
            'code' => 'WH004',
            'name' => 'Almacén de Cuarentena',
            'description' => 'Almacén especializado para productos en cuarentena',
            'type' => 'quarantine',
            'is_main' => false,
            'is_active' => true,
            'manager_name' => 'Ana López',
            'phone' => '+1-555-0104',
            'email' => 'cuarentena@almacen.com',
            'address' => 'Zona Especial 321',
            'city' => 'Tijuana',
            'state' => 'Baja California',
            'postal_code' => '22000',
            'country' => 'México',
            'total_capacity' => 1000.00,
            'used_capacity' => 150.00,
            'capacity_unit' => 'm3',
            'temperature_controlled' => true,
            'min_temperature' => -5.0,
            'max_temperature' => 25.0,
            'temperature_unit' => 'C',
            'security_level' => 'maximum',
            'storage_cost_per_unit' => 25.00,
            'handling_cost_per_unit' => 5.00,
            'operating_hours' => [
                'monday' => ['open' => '08:00', 'close' => '16:00'],
                'tuesday' => ['open' => '08:00', 'close' => '16:00'],
                'wednesday' => ['open' => '08:00', 'close' => '16:00'],
                'thursday' => ['open' => '08:00', 'close' => '16:00'],
                'friday' => ['open' => '08:00', 'close' => '16:00'],
                'saturday' => ['open' => null, 'close' => null],
                'sunday' => ['open' => null, 'close' => null]
            ],
            'notes' => 'Almacén especializado para productos en cuarentena y control sanitario',
            'metadata' => [
                'certifications' => ['FDA', 'SENASICA'],
                'equipment' => ['cámaras_aisladas', 'sistema_monitoreo_24h'],
                'dock_doors' => 1,
                'loading_bays' => 1,
                'office_space' => true,
                'isolation_chambers' => 4
            ]
        ]);

        // Crear algunas ubicaciones para el almacén principal
        $this->createLocationsForWarehouse($mainWarehouse);
        $this->createLocationsForWarehouse($secondaryWarehouse);
    }

    /**
     * Crear ubicaciones para un almacén
     */
    private function createLocationsForWarehouse(Warehouse $warehouse): void
    {
        $zones = ['A', 'B', 'C'];
        $aisles = ['01', '02', '03'];
        $racks = ['01', '02'];
        $bins = ['01', '02', '03'];

        foreach ($zones as $zone) {
            foreach ($aisles as $aisle) {
                foreach ($racks as $rack) {
                    foreach ($bins as $bin) {
                        Location::create([
                            'warehouse_id' => $warehouse->id,
                            'code' => "{$warehouse->code}-{$zone}-{$aisle}-{$rack}-{$bin}",
                            'name' => "Zona {$zone} - Pasillo {$aisle} - Rack {$rack} - Bin {$bin}",
                            'description' => 'Ubicación estándar de almacenamiento',
                            'type' => 'bin',
                            'aisle' => $aisle,
                            'rack' => $rack,
                            'bin' => $bin,
                            'capacity' => 100,
                            'current_stock' => rand(0, 20),
                            'is_active' => true,
                            'temperature_controlled' => $warehouse->temperature_controlled,
                            'security_level' => 'low'
                        ]);
                    }
                }
            }
        }
    }
}