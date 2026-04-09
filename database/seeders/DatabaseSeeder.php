<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            AllModulesPermissionsSeeder::class,
            SalesPermissionsSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            WarehouseSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            SupplierSeeder::class,
            SaleTemplateSeeder::class,
            CouponSeeder::class,
            // DarrowProductsSeeder::class,
        ]);
    }
}
