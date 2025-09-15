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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            
            // Estados y fechas
            $table->enum('status', ['draft', 'pending', 'approved', 'ordered', 'partial_received', 'received', 'cancelled']);
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            
            // Información financiera
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('MXN');
            
            // Términos y condiciones
            $table->integer('payment_terms_days')->default(30);
            $table->text('terms_conditions')->nullable();
            $table->text('delivery_instructions')->nullable();
            
            // Información adicional
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Aprobaciones
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['status', 'order_date']);
            $table->index(['supplier_id', 'status']);
            $table->index('expected_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
