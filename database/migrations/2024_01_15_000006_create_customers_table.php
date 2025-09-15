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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Colombia');
            $table->string('tax_id')->nullable()->unique();
            $table->enum('customer_type', ['individual', 'business', 'wholesale', 'retail'])->default('individual');
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->enum('payment_terms', ['cash', 'credit', 'net_15', 'net_30', 'net_60'])->default('cash');
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'customer_type']);
            $table->index('email');
            $table->index('phone');
            $table->index('tax_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};