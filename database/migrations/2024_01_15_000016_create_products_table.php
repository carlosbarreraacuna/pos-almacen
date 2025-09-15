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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->nullable();
            $table->string('unit_of_measure')->default('unit');
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            
            $table->index(['is_active', 'stock_quantity']);
            $table->index('category_id');
            $table->index('brand_id');
            $table->index('location_id');
            $table->index('sku');
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};