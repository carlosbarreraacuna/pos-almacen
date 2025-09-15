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
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            
            // Cantidades
            $table->integer('current_quantity'); // Cantidad actual en sistema
            $table->integer('adjusted_quantity'); // Cantidad real contada
            $table->integer('quantity_adjustment'); // Diferencia (calculada)
            
            // Valores
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('value_adjustment', 12, 2)->default(0); // Valor del ajuste
            
            // Razón específica del item
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['stock_adjustment_id', 'product_id']);
            $table->index('product_id');
            
            // Constraint único para evitar duplicados
            $table->unique(['stock_adjustment_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};