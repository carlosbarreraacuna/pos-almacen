<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();   // JSON o string
            $table->string('group')->default('general');  // Agrupa settings por módulo
            $table->string('label')->nullable();          // Etiqueta legible
            $table->string('type')->default('string');    // string | number | boolean | json
            $table->timestamps();
        });

        // Defaults de precios
        DB::table('settings')->insert([
            ['key' => 'pricing.default_iva',           'value' => '19',   'group' => 'pricing', 'label' => 'IVA por defecto (%)',               'type' => 'number', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'pricing.default_flete',         'value' => '0',    'group' => 'pricing', 'label' => 'Flete por defecto ($)',              'type' => 'number', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'pricing.default_profit_margin', 'value' => '30',   'group' => 'pricing', 'label' => 'Margen de ganancia por defecto (%)', 'type' => 'number', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
