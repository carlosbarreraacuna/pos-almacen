<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Costo de flete por unidad (se suma al costo base antes de calcular precio)
            $table->decimal('freight_cost', 10, 2)->default(0)->after('cost_price');
            // Porcentaje de ganancia deseado para este producto (0–1000%)
            $table->decimal('profit_margin', 6, 2)->default(0)->after('freight_cost');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['freight_cost', 'profit_margin']);
        });
    }
};
