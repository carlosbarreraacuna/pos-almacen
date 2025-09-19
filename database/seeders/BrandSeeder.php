<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run()
    {
        Brand::create([
            'name' => 'Marca Ejemplo',
            'description' => 'Descripción de la marca ejemplo',
            'logo_url' => null,
            'website' => 'https://marcaejemplo.com',
            'contact_email' => 'contacto@marcaejemplo.com',
            'contact_phone' => '3001234567',
            'is_active' => true,
        ]);
    }
}
