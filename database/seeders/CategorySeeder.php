<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'BANDAS DE FRENO',
            'BATERÍAS',
            'CADENAS',
            'CORREA DE TRANSMISIÓN',
            'CUNAS DE DIRECCIÓN',
            'DISCOS DE CLUTCH',
            'EJES',
            'FILTROS DE ACEITE',
            'FILTROS DE AIRE - ESPUMA',
            'FILTROS DE AIRE - PANEL',
            'FILTROS DE GASOLINA',
            'GUARDABARROS',
            'KIT DE ARRASTRE',
            'KIT DE VÁLVULAS',
            'MANIGUETAS',
            'MANUBRIOS',
            'PASTILLAS DE FRENO',
            'PATA LATERAL',
            'RAMALES ELÉCTRICOS',
            'VARILLA DE FRENO',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
