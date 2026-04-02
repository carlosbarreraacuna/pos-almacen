<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Repuestos',
                'description' => 'Repuestos y accesorios para motos',
                'is_active' => true,
            ],
            [
                'name' => 'Lubricantes',
                'description' => 'Aceites y lubricantes para motocicletas',
                'is_active' => true,
            ],
            [
                'name' => 'Accesorios',
                'description' => 'Accesorios y equipamiento para motociclistas',
                'is_active' => true,
            ],
            [
                'name' => 'Neumáticos',
                'description' => 'Llantas y neumáticos para motos',
                'is_active' => true,
            ],
            [
                'name' => 'Eléctricos',
                'description' => 'Componentes eléctricos y electrónicos',
                'is_active' => true,
            ],
            [
                'name' => 'Frenos',
                'description' => 'Sistema de frenos y componentes',
                'is_active' => true,
            ],
            [
                'name' => 'Suspensión',
                'description' => 'Componentes de suspensión',
                'is_active' => true,
            ],
            [
                'name' => 'Motor',
                'description' => 'Partes y componentes del motor',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::create($categoryData);
        }
    }
}
