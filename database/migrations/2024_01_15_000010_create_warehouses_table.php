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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 50)->default('standard'); // standard, cold_storage, hazmat, etc.
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Información de contacto
            $table->string('manager_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            // Dirección
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('México');
            
            // Capacidad y configuración
            $table->decimal('total_capacity', 12, 2)->nullable(); // m³ o unidades
            $table->decimal('used_capacity', 12, 2)->default(0);
            $table->string('capacity_unit', 20)->default('m3'); // m3, units, pallets
            
            // Configuración de temperatura
            $table->boolean('temperature_controlled')->default(false);
            $table->decimal('min_temperature', 5, 2)->nullable();
            $table->decimal('max_temperature', 5, 2)->nullable();
            $table->string('temperature_unit', 10)->default('C'); // C, F
            
            // Configuración de seguridad
            $table->string('security_level', 20)->default('standard'); // low, standard, high, maximum
            $table->boolean('hazmat_approved')->default(false);
            $table->boolean('requires_certification')->default(false);
            
            // Horarios de operación
            $table->json('operating_hours')->nullable();
            $table->string('timezone', 50)->default('America/Mexico_City');
            
            // Configuración de costos
            $table->decimal('storage_cost_per_unit', 8, 2)->default(0);
            $table->decimal('handling_cost_per_unit', 8, 2)->default(0);
            $table->string('cost_currency', 3)->default('MXN');
            
            // Metadatos y notas
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['is_active', 'type']);
            $table->index(['city', 'state']);
            $table->index('is_main');
            $table->index('temperature_controlled');
            $table->index('security_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};