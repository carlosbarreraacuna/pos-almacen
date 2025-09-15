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
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('requires_electronic_invoice')->default(false);
            $table->string('invoice_type')->default('standard'); // standard, export, contingency
            $table->string('operation_type')->default('sale'); // sale, return, credit_note, debit_note
            $table->string('payment_form')->default('cash'); // cash, credit, mixed
            $table->integer('payment_due_days')->nullable();
            $table->text('observations')->nullable();
            $table->boolean('is_electronic_invoice_sent')->default(false);
            $table->timestamp('electronic_invoice_sent_at')->nullable();
            $table->string('dian_status')->nullable(); // pending, sent, accepted, rejected
            $table->text('dian_errors')->nullable();
            
            $table->index(['requires_electronic_invoice', 'is_electronic_invoice_sent']);
            $table->index('dian_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'requires_electronic_invoice',
                'invoice_type',
                'operation_type',
                'payment_form',
                'payment_due_days',
                'observations',
                'is_electronic_invoice_sent',
                'electronic_invoice_sent_at',
                'dian_status',
                'dian_errors'
            ]);
        });
    }
};
