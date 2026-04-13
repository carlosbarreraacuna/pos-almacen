<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                  ->constrained('customers')->nullOnDelete();
            $table->string('tracking_number')->nullable()->after('shipped_at');
            $table->string('shipping_company')->nullable()->after('tracking_number');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'tracking_number', 'shipping_company']);
        });
    }
};
