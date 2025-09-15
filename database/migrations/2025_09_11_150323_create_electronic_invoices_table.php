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
        Schema::create('electronic_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->string('cufe')->unique(); // Código Único de Facturación Electrónica
            $table->string('invoice_number')->unique();
            $table->string('prefix')->nullable();
            $table->bigInteger('consecutive_number');
            $table->datetime('issue_date');
            $table->datetime('due_date')->nullable();
            $table->string('currency', 3)->default('COP');
            $table->decimal('exchange_rate', 10, 4)->default(1);
            
            // Información del emisor (empresa)
            $table->string('issuer_nit');
            $table->string('issuer_name');
            $table->string('issuer_address');
            $table->string('issuer_city');
            $table->string('issuer_department');
            $table->string('issuer_country', 2)->default('CO');
            $table->string('issuer_phone')->nullable();
            $table->string('issuer_email')->nullable();
            
            // Información del adquiriente (cliente)
            $table->string('customer_document_type');
            $table->string('customer_document_number');
            $table->string('customer_name');
            $table->string('customer_address')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_department')->nullable();
            $table->string('customer_country', 2)->default('CO');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            
            // Totales
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            
            // Estados DIAN
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'cancelled'])->default('draft');
            $table->string('dian_response_code')->nullable();
            $table->text('dian_response_message')->nullable();
            $table->datetime('sent_to_dian_at')->nullable();
            $table->datetime('accepted_at')->nullable();
            
            // Archivos generados
            $table->text('xml_content')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('xml_path')->nullable();
            
            $table->timestamps();
            
            $table->index(['status', 'issue_date']);
            $table->index('customer_document_number');
            $table->index('consecutive_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('electronic_invoices');
    }
};
