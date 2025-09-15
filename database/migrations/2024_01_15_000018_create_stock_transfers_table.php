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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            
            // Almacenes y ubicaciones
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->foreignId('from_location_id')->nullable()->constrained('locations');
            $table->foreignId('to_location_id')->nullable()->constrained('locations');
            
            // Usuario y fechas
            $table->foreignId('user_id')->constrained('users');
            $table->datetime('transfer_date');
            $table->datetime('expected_date')->nullable();
            $table->datetime('shipped_date')->nullable();
            $table->datetime('received_date')->nullable();
            
            // Estado y tipo
            $table->enum('status', ['pending', 'approved', 'in_transit', 'completed', 'cancelled'])
                  ->default('pending');
            $table->enum('type', ['internal', 'external', 'emergency', 'rebalance'])
                  ->default('internal');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                  ->default('normal');
            
            // Información de envío
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('shipping_method')->nullable();
            
            // Totales
            $table->integer('total_items')->default(0);
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            
            // Aprobación
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->datetime('approved_at')->nullable();
            
            // Recepción
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->text('receiving_notes')->nullable();
            
            // Notas y metadatos
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['status', 'transfer_date']);
            $table->index(['from_warehouse_id', 'to_warehouse_id']);
            $table->index(['transfer_date', 'expected_date']);
            $table->index(['user_id', 'status']);
            $table->index('type');
            $table->index('priority');
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};