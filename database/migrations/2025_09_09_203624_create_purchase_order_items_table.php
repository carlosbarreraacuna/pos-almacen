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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            
            // Cantidades
            $table->decimal('quantity_ordered', 10, 3);
            $table->decimal('quantity_received', 10, 3)->default(0);
            $table->decimal('quantity_pending', 10, 3)->default(0);
            $table->string('unit_of_measure', 10)->default('pcs');
            
            // Precios
            $table->decimal('unit_cost', 10, 4);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            
            // Información adicional
            $table->string('supplier_product_code')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Fechas de entrega
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['purchase_order_id', 'product_id']);
            $table->index('expected_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
