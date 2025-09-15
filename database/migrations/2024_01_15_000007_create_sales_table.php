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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->datetime('sale_date');
            $table->enum('status', ['draft', 'pending', 'completed', 'cancelled', 'refunded'])->default('draft');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check', 'credit'])->default('cash');
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->datetime('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'sale_date']);
            $table->index(['payment_status', 'due_date']);
            $table->index('sale_date');
            $table->index('user_id');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};