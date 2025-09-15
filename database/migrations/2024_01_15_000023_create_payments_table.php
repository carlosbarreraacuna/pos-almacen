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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check', 'credit']);
            $table->decimal('amount', 10, 2);
            $table->datetime('payment_date');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamps();
            
            $table->index(['sale_id', 'status']);
            $table->index(['payment_method', 'payment_date']);
            $table->index('payment_date');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};