<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = Category::first();
        $brand = Brand::first();
        
        $products = [
            ['name' => 'Café Premium', 'price' => 15000, 'cost' => 8000],
            ['name' => 'Pan Integral', 'price' => 8000, 'cost' => 4000],
            ['name' => 'Jugo Natural', 'price' => 5000, 'cost' => 2500],
            ['name' => 'Bolígrafos Pack x5', 'price' => 2000, 'cost' => 1000],
            ['name' => 'Cuaderno A4', 'price' => 12000, 'cost' => 6000],
            ['name' => 'Calculadora Básica', 'price' => 25000, 'cost' => 15000],
            ['name' => 'Detergente Líquido', 'price' => 8500, 'cost' => 5000],
            ['name' => 'Jabón Antibacterial', 'price' => 15000, 'cost' => 8000],
            ['name' => 'Desinfectante Multiusos', 'price' => 22000, 'cost' => 12000],
            ['name' => 'Papel Higiénico x4', 'price' => 18000, 'cost' => 10000]
        ];
        
        foreach ($products as $index => $productData) {
            Product::create([
                'name' => $productData['name'],
                'description' => 'Descripción del producto ' . $productData['name'],
                'sku' => 'PROD' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'barcode' => '123456789' . ($index + 1),
                'category_id' => $category ? $category->id : 1,
                'brand_id' => $brand ? $brand->id : 1,
                'unit_price' => $productData['price'],
                'cost_price' => $productData['cost'],
                'stock_quantity' => rand(50, 200),
                'min_stock_level' => 10,
                'max_stock_level' => 500,
                'unit_of_measure' => 'unit',
                'is_active' => true,
                'tax_rate' => 19.00
            ]);
        }
        
        $this->command->info('Productos creados exitosamente.');
    }
}
