<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->text('shipping_address');
            $table->string('shipping_city', 100);
            $table->string('shipping_state', 100);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 50);
            $table->string('payment_reference')->nullable();
            $table->enum('status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('web'); // web, pos, admin
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            $table->index('order_number');
            $table->index('customer_email');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
