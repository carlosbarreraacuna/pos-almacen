<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('frequency_count')->default(1); // Cuántas veces se ha vendido este producto a este cliente
            $table->decimal('average_quantity', 8, 2); // Cantidad promedio comprada
            $table->decimal('last_price', 10, 2); // Último precio de venta
            $table->decimal('average_price', 10, 2); // Precio promedio
            $table->timestamp('last_sale_date'); // Fecha de la última venta
            $table->timestamp('first_sale_date'); // Fecha de la primera venta
            $table->integer('days_between_purchases')->nullable(); // Días promedio entre compras
            $table->json('seasonal_data')->nullable(); // Datos estacionales (meses de mayor compra)
            $table->decimal('total_revenue', 15, 2)->default(0); // Ingresos totales de este producto con este cliente
            $table->timestamps();
            
            $table->unique(['customer_id', 'product_id']);
            $table->index(['customer_id', 'frequency_count']);
            $table->index(['product_id', 'frequency_count']);
            $table->index('last_sale_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_histories');
    }
};
