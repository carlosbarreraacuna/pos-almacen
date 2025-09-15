<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'adjustment_number',
        'warehouse_id',
        'location_id',
        'user_id',
        'adjustment_date',
        'type',
        'reason',
        'status',
        'total_items',
        'total_value_adjustment',
        'notes',
        'approved_by',
        'approved_at',
        'metadata'
    ];

    protected $casts = [
        'adjustment_date' => 'datetime',
        'total_items' => 'integer',
        'total_value_adjustment' => 'decimal:2',
        'approved_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Tipos de ajuste
    const TYPE_INCREASE = 'increase';
    const TYPE_DECREASE = 'decrease';
    const TYPE_RECOUNT = 'recount';
    const TYPE_DAMAGE = 'damage';
    const TYPE_EXPIRY = 'expiry';
    const TYPE_THEFT = 'theft';
    const TYPE_CORRECTION = 'correction';

    // Estados
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_APPLIED = 'applied';
    const STATUS_CANCELLED = 'cancelled';

    // Razones
    const REASON_PHYSICAL_COUNT = 'physical_count';
    const REASON_DAMAGED_GOODS = 'damaged_goods';
    const REASON_EXPIRED_GOODS = 'expired_goods';
    const REASON_THEFT_LOSS = 'theft_loss';
    const REASON_SYSTEM_ERROR = 'system_error';
    const REASON_SUPPLIER_ERROR = 'supplier_error';
    const REASON_FOUND_GOODS = 'found_goods';
    const REASON_OTHER = 'other';

    /**
     * Relación con almacén
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relación con ubicación
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Relación con usuario que creó el ajuste
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con usuario que aprobó
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relación con items del ajuste
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    /**
     * Scope para ajustes pendientes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope para ajustes aprobados
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope para ajustes aplicados
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    /**
     * Scope por rango de fechas
     */
    public function scopeByDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('adjustment_date', [$startDate, $endDate]);
    }

    /**
     * Scope para ajustes de hoy
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('adjustment_date', today());
    }

    /**
     * Scope por tipo
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope por almacén
     */
    public function scopeByWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Generar número de ajuste
     */
    public static function generateAdjustmentNumber(): string
    {
        $prefix = 'ADJ';
        $date = now()->format('Ymd');
        
        $lastAdjustment = self::where('adjustment_number', 'like', $prefix . $date . '%')
            ->orderBy('adjustment_number', 'desc')
            ->first();

        if ($lastAdjustment) {
            $lastNumber = (int) substr($lastAdjustment->adjustment_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Aprobar ajuste
     */
    public function approve(User $approver): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $approver->id;
        $this->approved_at = now();
        
        return $this->save();
    }

    /**
     * Aplicar ajuste al inventario
     */
    public function apply(): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        \DB::transaction(function () {
            foreach ($this->items as $item) {
                // Actualizar stock del producto
                $productStock = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('location_id', $this->location_id)
                    ->first();

                if ($productStock) {
                    $productStock->quantity += $item->quantity_adjustment;
                    $productStock->save();
                } else {
                    // Crear nuevo registro de stock si no existe
                    ProductStock::create([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $this->warehouse_id,
                        'location_id' => $this->location_id,
                        'quantity' => max(0, $item->quantity_adjustment)
                    ]);
                }

                // Registrar movimiento de stock
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $this->warehouse_id,
                    'location_id' => $this->location_id,
                    'type' => 'adjustment',
                    'quantity' => abs($item->quantity_adjustment),
                    'direction' => $item->quantity_adjustment >= 0 ? 'in' : 'out',
                    'reference_type' => 'stock_adjustment',
                    'reference_id' => $this->id,
                    'user_id' => $this->user_id,
                    'notes' => "Ajuste: {$this->adjustment_number}"
                ]);
            }

            $this->status = self::STATUS_APPLIED;
            $this->save();
        });

        return true;
    }

    /**
     * Cancelar ajuste
     */
    public function cancel(): bool
    {
        if (in_array($this->status, [self::STATUS_APPLIED, self::STATUS_CANCELLED])) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Calcular totales
     */
    public function calculateTotals(): void
    {
        $this->total_items = $this->items()->count();
        $this->total_value_adjustment = $this->items()->sum('value_adjustment');
        $this->save();
    }

    /**
     * Obtener tipos disponibles
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_INCREASE => 'Incremento',
            self::TYPE_DECREASE => 'Decremento',
            self::TYPE_RECOUNT => 'Reconteo',
            self::TYPE_DAMAGE => 'Daño',
            self::TYPE_EXPIRY => 'Vencimiento',
            self::TYPE_THEFT => 'Robo/Pérdida',
            self::TYPE_CORRECTION => 'Corrección'
        ];
    }

    /**
     * Obtener estados disponibles
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_APPLIED => 'Aplicado',
            self::STATUS_CANCELLED => 'Cancelado'
        ];
    }

    /**
     * Obtener razones disponibles
     */
    public static function getReasons(): array
    {
        return [
            self::REASON_PHYSICAL_COUNT => 'Conteo físico',
            self::REASON_DAMAGED_GOODS => 'Mercancía dañada',
            self::REASON_EXPIRED_GOODS => 'Mercancía vencida',
            self::REASON_THEFT_LOSS => 'Robo/Pérdida',
            self::REASON_SYSTEM_ERROR => 'Error del sistema',
            self::REASON_SUPPLIER_ERROR => 'Error del proveedor',
            self::REASON_FOUND_GOODS => 'Mercancía encontrada',
            self::REASON_OTHER => 'Otro'
        ];
    }

    /**
     * Obtener nombre del tipo
     */
    public function getTypeNameAttribute(): string
    {
        $types = self::getTypes();
        return $types[$this->type] ?? $this->type;
    }

    /**
     * Obtener nombre del estado
     */
    public function getStatusNameAttribute(): string
    {
        $statuses = self::getStatuses();
        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Obtener nombre de la razón
     */
    public function getReasonNameAttribute(): string
    {
        $reasons = self::getReasons();
        return $reasons[$this->reason] ?? $this->reason;
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($adjustment) {
            if (empty($adjustment->adjustment_number)) {
                $adjustment->adjustment_number = self::generateAdjustmentNumber();
            }
            if (empty($adjustment->adjustment_date)) {
                $adjustment->adjustment_date = now();
            }
        });

        static::saved(function ($adjustment) {
            $adjustment->calculateTotals();
        });
    }
}