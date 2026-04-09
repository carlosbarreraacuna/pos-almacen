<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        Brand::firstOrCreate(
            ['name' => 'DARROW'],
            ['is_active' => true]
        );
    }
}
