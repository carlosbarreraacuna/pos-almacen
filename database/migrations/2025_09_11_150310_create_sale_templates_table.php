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
        Schema::create('sale_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->json('items'); // Array de productos con cantidades predefinidas
            $table->string('payment_method')->default('cash');
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(19);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_templates');
    }
};
