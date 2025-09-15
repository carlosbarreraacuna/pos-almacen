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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->enum('type', ['manufacturer', 'distributor', 'wholesaler', 'service_provider']);
            $table->boolean('is_active')->default(true);
            
            // Información de contacto
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            
            // Dirección
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            
            // Términos comerciales
            $table->integer('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->enum('payment_method', ['cash', 'check', 'transfer', 'credit']);
            $table->string('bank_account')->nullable();
            
            // Configuraciones
            $table->integer('lead_time_days')->default(7);
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->enum('currency', ['USD', 'MXN', 'EUR'])->default('MXN');
            
            // Calificación y notas
            $table->integer('rating')->nullable()->comment('1-5 stars');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['type', 'is_active']);
            $table->index('payment_terms_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
