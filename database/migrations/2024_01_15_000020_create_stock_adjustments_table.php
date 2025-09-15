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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            
            // Ubicación del ajuste
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            
            // Usuario y fechas
            $table->foreignId('user_id')->constrained('users');
            $table->datetime('adjustment_date');
            
            // Tipo y razón
            $table->enum('type', ['increase', 'decrease', 'recount', 'damage', 'expiry', 'theft', 'correction'])
                  ->default('correction');
            $table->enum('reason', [
                'physical_count', 'damaged_goods', 'expired_goods', 'theft_loss', 
                'system_error', 'supplier_error', 'found_goods', 'other'
            ])->default('physical_count');
            
            // Estado
            $table->enum('status', ['draft', 'pending', 'approved', 'applied', 'cancelled'])
                  ->default('draft');
            
            // Totales
            $table->integer('total_items')->default(0);
            $table->decimal('total_value_adjustment', 12, 2)->default(0);
            
            // Aprobación
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->datetime('approved_at')->nullable();
            
            // Notas y metadatos
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['status', 'adjustment_date']);
            $table->index(['warehouse_id', 'location_id']);
            $table->index(['type', 'reason']);
            $table->index(['user_id', 'status']);
            $table->index('adjustment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};