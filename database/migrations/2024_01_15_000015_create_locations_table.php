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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['warehouse', 'zone', 'aisle', 'rack', 'shelf', 'bin'])->default('bin');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('aisle')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->string('bin')->nullable();
            $table->integer('capacity')->nullable();
            $table->integer('current_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('temperature_controlled')->default(false);
            $table->enum('security_level', ['low', 'medium', 'high'])->default('low');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('parent_id')->references('id')->on('locations')->onDelete('set null');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->index(['type', 'is_active']);
            $table->index('warehouse_id');
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};