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
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            
            // Cantidades
            $table->integer('quantity'); // Cantidad solicitada
            $table->integer('quantity_received')->nullable()->default(0); // Cantidad recibida
            
            // Notas específicas del item
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['stock_transfer_id', 'product_id']);
            $table->index('product_id');
            
            // Constraint único para evitar duplicados
            $table->unique(['stock_transfer_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};