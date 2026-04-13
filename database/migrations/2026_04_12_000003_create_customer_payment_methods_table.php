<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('card_brand'); // VISA, MASTERCARD, AMEX
            $table->string('last_four', 4);
            $table->string('holder_name');
            $table->string('exp_month', 2);
            $table->string('exp_year', 4);
            $table->string('token')->nullable(); // token de pasarela de pago
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_methods');
    }
};
