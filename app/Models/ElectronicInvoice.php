<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ElectronicInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'cufe',
        'invoice_number',
        'prefix',
        'consecutive_number',
        'issue_date',
        'due_date',
        'currency',
        'exchange_rate',
        'issuer_nit',
        'issuer_name',
        'issuer_address',
        'issuer_city',
        'issuer_department',
        'issuer_country',
        'issuer_phone',
        'issuer_email',
        'customer_document_type',
        'customer_document_number',
        'customer_name',
        'customer_address',
        'customer_city',
        'customer_department',
        'customer_country',
        'customer_phone',
        'customer_email',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'dian_response_code',
        'dian_response_message',
        'sent_to_dian_at',
        'accepted_at',
        'xml_content',
        'pdf_path',
        'xml_path'
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'due_date' => 'datetime',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sent_to_dian_at' => 'datetime',
        'accepted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Estados de la factura electrónica
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    // Tipos de documento de identidad
    const DOCUMENT_TYPES = [
        'CC' => 'Cédula de Ciudadanía',
        'CE' => 'Cédula de Extranjería',
        'NIT' => 'Número de Identificación Tributaria',
        'TI' => 'Tarjeta de Identidad',
        'PP' => 'Pasaporte',
        'RC' => 'Registro Civil',
        'TE' => 'Tarjeta de Extranjería'
    ];

    /**
     * Relación con la venta
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Scope para facturas por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para facturas pendientes de envío
     */
    public function scopePendingToSend($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope para facturas enviadas
     */
    public function scopeSent($query)
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_ACCEPTED, self::STATUS_REJECTED]);
    }

    /**
     * Genera el CUFE (Código Único de Facturación Electrónica)
     */
    public function generateCufe()
    {
        $data = [
            $this->invoice_number,
            $this->issue_date->format('Y-m-d'),
            $this->issue_date->format('H:i:s'),
            number_format($this->total_amount, 2, '.', ''),
            '01', // Código del impuesto (IVA)
            number_format($this->tax_amount, 2, '.', ''),
            '04', // Código del impuesto (INC)
            '0.00', // Valor del impuesto INC
            '03', // Código del impuesto (ICA)
            '0.00', // Valor del impuesto ICA
            $this->customer_document_number,
            config('dian.technical_key', 'default_key'), // Clave técnica
            config('dian.environment', '2') // Ambiente (1=Producción, 2=Pruebas)
        ];
        
        $concatenated = implode('', $data);
        $this->cufe = sha1($concatenated);
        
        return $this->cufe;
    }

    /**
     * Genera el número de factura consecutivo
     */
    public static function generateConsecutiveNumber($prefix = null)
    {
        $lastInvoice = self::where('prefix', $prefix)
            ->orderBy('consecutive_number', 'desc')
            ->first();
        
        return $lastInvoice ? $lastInvoice->consecutive_number + 1 : 1;
    }

    /**
     * Genera el número completo de factura
     */
    public function generateInvoiceNumber()
    {
        if (!$this->consecutive_number) {
            $this->consecutive_number = self::generateConsecutiveNumber($this->prefix);
        }
        
        $this->invoice_number = ($this->prefix ? $this->prefix : '') . str_pad($this->consecutive_number, 8, '0', STR_PAD_LEFT);
        
        return $this->invoice_number;
    }

    /**
     * Valida los campos requeridos por la DIAN
     */
    public function validateDianFields()
    {
        $errors = [];
        
        // Campos obligatorios del emisor
        if (empty($this->issuer_nit)) $errors[] = 'NIT del emisor es requerido';
        if (empty($this->issuer_name)) $errors[] = 'Nombre del emisor es requerido';
        if (empty($this->issuer_address)) $errors[] = 'Dirección del emisor es requerida';
        if (empty($this->issuer_city)) $errors[] = 'Ciudad del emisor es requerida';
        if (empty($this->issuer_department)) $errors[] = 'Departamento del emisor es requerido';
        
        // Campos obligatorios del cliente
        if (empty($this->customer_document_type)) $errors[] = 'Tipo de documento del cliente es requerido';
        if (empty($this->customer_document_number)) $errors[] = 'Número de documento del cliente es requerido';
        if (empty($this->customer_name)) $errors[] = 'Nombre del cliente es requerido';
        
        // Validar tipo de documento
        if (!array_key_exists($this->customer_document_type, self::DOCUMENT_TYPES)) {
            $errors[] = 'Tipo de documento del cliente no válido';
        }
        
        // Campos obligatorios de la factura
        if (empty($this->issue_date)) $errors[] = 'Fecha de emisión es requerida';
        if ($this->subtotal <= 0) $errors[] = 'Subtotal debe ser mayor a cero';
        if ($this->total_amount <= 0) $errors[] = 'Total debe ser mayor a cero';
        
        return $errors;
    }

    /**
     * Marca la factura como enviada a la DIAN
     */
    public function markAsSent($responseCode = null, $responseMessage = null)
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_to_dian_at' => Carbon::now(),
            'dian_response_code' => $responseCode,
            'dian_response_message' => $responseMessage
        ]);
    }

    /**
     * Marca la factura como aceptada por la DIAN
     */
    public function markAsAccepted($responseCode = null, $responseMessage = null)
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => Carbon::now(),
            'dian_response_code' => $responseCode,
            'dian_response_message' => $responseMessage
        ]);
    }

    /**
     * Marca la factura como rechazada por la DIAN
     */
    public function markAsRejected($responseCode = null, $responseMessage = null)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'dian_response_code' => $responseCode,
            'dian_response_message' => $responseMessage
        ]);
    }

    /**
     * Verifica si la factura puede ser enviada a la DIAN
     */
    public function canBeSent()
    {
        return $this->status === self::STATUS_DRAFT && empty($this->validateDianFields());
    }

    /**
     * Obtiene el estado en español
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_SENT => 'Enviada',
            self::STATUS_ACCEPTED => 'Aceptada',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_CANCELLED => 'Cancelada'
        ];
        
        return $labels[$this->status] ?? 'Desconocido';
    }
}