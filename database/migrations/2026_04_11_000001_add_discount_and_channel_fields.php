<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Descuento en productos
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percentage')->default(0)->after('unit_price')
                ->comment('Porcentaje de descuento 0-100. Si > 0 el producto aparece en ofertas.');
        });

        // Canal de venta en sales (POS vs web)
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('sale_channel', ['pos', 'web'])->default('pos')->after('status')
                ->comment('pos = venta en tienda fisica, web = venta por tienda online');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('discount_percentage');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('sale_channel');
        });
    }
};
