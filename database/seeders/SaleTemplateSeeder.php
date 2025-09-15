<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SaleTemplate;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;

class SaleTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener usuarios y clientes existentes
        $users = User::all();
        $customers = Customer::all();
        $products = Product::take(10)->get();
        
        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->warn('No hay usuarios o productos disponibles para crear plantillas.');
            return;
        }
        
        $templates = [
            [
                'name' => 'Combo Desayuno Completo',
                'description' => 'Plantilla para venta de desayuno con café, pan y jugo',
                'items' => [
                    ['product_id' => $products[0]->id, 'quantity' => 2, 'price' => $products[0]->unit_price, 'discount' => 0],
                    ['product_id' => $products[1]->id, 'quantity' => 1, 'price' => $products[1]->unit_price, 'discount' => 500],
                    ['product_id' => $products[2]->id, 'quantity' => 1, 'price' => $products[2]->unit_price, 'discount' => 0]
                ],
                'payment_method' => 'cash',
                'usage_count' => 15
            ],
            [
                'name' => 'Kit Oficina Básico',
                'description' => 'Plantilla para venta de artículos de oficina básicos',
                'items' => [
                    ['product_id' => $products[3]->id, 'quantity' => 5, 'price' => $products[3]->unit_price, 'discount' => 200],
                    ['product_id' => $products[4]->id, 'quantity' => 2, 'price' => $products[4]->unit_price, 'discount' => 0],
                    ['product_id' => $products[5]->id, 'quantity' => 1, 'price' => $products[5]->unit_price, 'discount' => 2500]
                ],
                'payment_method' => 'card',
                'usage_count' => 8
            ],
            [
                'name' => 'Combo Limpieza Hogar',
                'description' => 'Plantilla para productos de limpieza del hogar',
                'items' => [
                    ['product_id' => $products[6]->id, 'quantity' => 3, 'price' => $products[6]->unit_price, 'discount' => 500],
                    ['product_id' => $products[7]->id, 'quantity' => 2, 'price' => $products[7]->unit_price, 'discount' => 1000],
                    ['product_id' => $products[8]->id, 'quantity' => 1, 'price' => $products[8]->unit_price, 'discount' => 0]
                ],
                'payment_method' => 'transfer',
                'usage_count' => 12
            ]
        ];
        
        foreach ($templates as $templateData) {
            SaleTemplate::create([
                'name' => $templateData['name'],
                'description' => $templateData['description'],
                'user_id' => $users->random()->id,
                'customer_id' => $customers->isNotEmpty() ? $customers->random()->id : null,
                'items' => json_encode($templateData['items']),
                'payment_method' => $templateData['payment_method'],
                'discount_percentage' => 0,
                'tax_rate' => 19,
                'notes' => 'Plantilla creada automáticamente',
                'is_active' => true,
                'usage_count' => $templateData['usage_count'],
                'last_used_at' => now()->subDays(rand(1, 30))
            ]);
        }
        
        $this->command->info('Plantillas de venta creadas exitosamente.');
    }
}
