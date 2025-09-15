<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'from_location_id',
        'to_location_id',
        'status',
        'transfer_date',
        'expected_date',
        'completed_date',
        'notes',
        'created_by',
        'approved_by',
        'received_by',
        'total_items',
        'total_quantity'
    ];

    protected $casts = [
        'transfer_date' => 'datetime',
        'expected_date' => 'datetime',
        'completed_date' => 'datetime',
        'total_items' => 'integer',
        'total_quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Estados de transferencia
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Relación con almacén de origen
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * Relación con almacén de destino
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * Relación con ubicación de origen
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * Relación con ubicación de destino
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * Relación con usuario creador
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relación con usuario aprobador
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relación con usuario receptor
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Relación con items de transferencia
     */
    public function transferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * Scope para transferencias pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope para transferencias en tránsito
     */
    public function scopeInTransit($query)
    {
        return $query->where('status', self::STATUS_IN_TRANSIT);
    }

    /**
     * Scope para transferencias completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope para transferencias por rango de fechas
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transfer_date', [$startDate, $endDate]);
    }

    /**
     * Scope para transferencias de hoy
     */
    public function scopeToday($query)
    {
        return $query->whereDate('transfer_date', today());
    }

    /**
     * Scope para transferencias por almacén de origen
     */
    public function scopeFromWarehouse($query, $warehouseId)
    {
        return $query->where('from_warehouse_id', $warehouseId);
    }

    /**
     * Scope para transferencias por almacén de destino
     */
    public function scopeToWarehouse($query, $warehouseId)
    {
        return $query->where('to_warehouse_id', $warehouseId);
    }

    /**
     * Generar número de transferencia
     */
    public static function generateTransferNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $count = static::whereYear('created_at', $year)
                      ->whereMonth('created_at', $month)
                      ->count() + 1;
        
        return "TR{$year}{$month}" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Aprobar transferencia
     */
    public function approve(int $userId): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }

        $this->status = self::STATUS_PENDING;
        $this->approved_by = $userId;
        $this->transfer_date = now();
        
        return $this->save();
    }

    /**
     * Iniciar transferencia (poner en tránsito)
     */
    public function startTransfer(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        // Verificar stock disponible
        foreach ($this->transferItems as $item) {
            $product = $item->product;
            if ($product->stock < $item->quantity) {
                throw new \Exception("Stock insuficiente para el producto: {$product->name}");
            }
        }

        $this->status = self::STATUS_IN_TRANSIT;
        return $this->save();
    }

    /**
     * Completar transferencia
     */
    public function complete(int $userId): bool
    {
        if ($this->status !== self::STATUS_IN_TRANSIT) {
            return false;
        }

        try {
            \DB::beginTransaction();

            // Procesar cada item de la transferencia
            foreach ($this->transferItems as $item) {
                $product = $item->product;
                
                // Reducir stock en ubicación de origen
                if ($this->from_location_id) {
                    // Si hay ubicación específica, actualizar solo ese producto en esa ubicación
                    $product->decrement('stock', $item->quantity);
                } else {
                    // Si es transferencia entre almacenes, reducir del stock general
                    $product->decrement('stock', $item->quantity);
                }

                // Crear movimiento de salida
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'transfer_out',
                    'reference_id' => $this->id,
                    'user_id' => $userId,
                    'notes' => "Transferencia #{$this->transfer_number} - Salida"
                ]);

                // Si hay ubicación de destino específica, mover el producto
                if ($this->to_location_id) {
                    // Crear o actualizar producto en ubicación de destino
                    $destinationProduct = Product::where('sku', $product->sku)
                        ->where('location_id', $this->to_location_id)
                        ->first();

                    if ($destinationProduct) {
                        $destinationProduct->increment('stock', $item->quantity);
                    } else {
                        // Crear nuevo registro de producto en la ubicación de destino
                        Product::create([
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'description' => $product->description,
                            'category_id' => $product->category_id,
                            'brand_id' => $product->brand_id,
                            'location_id' => $this->to_location_id,
                            'cost_price' => $product->cost_price,
                            'selling_price' => $product->selling_price,
                            'stock' => $item->quantity,
                            'min_stock' => $product->min_stock,
                            'max_stock' => $product->max_stock,
                            'is_active' => $product->is_active
                        ]);
                    }
                } else {
                    // Si es transferencia entre almacenes sin ubicación específica
                    // El stock se manejará a nivel de almacén
                    $product->increment('stock', $item->quantity);
                }

                // Crear movimiento de entrada
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reference_type' => 'transfer_in',
                    'reference_id' => $this->id,
                    'user_id' => $userId,
                    'notes' => "Transferencia #{$this->transfer_number} - Entrada"
                ]);
            }

            $this->status = self::STATUS_COMPLETED;
            $this->completed_date = now();
            $this->received_by = $userId;
            $this->save();

            \DB::commit();
            return true;

        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancelar transferencia
     */
    public function cancel(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
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
        $this->total_items = $this->transferItems()->count();
        $this->total_quantity = $this->transferItems()->sum('quantity');
    }

    /**
     * Verificar si está vencida
     */
    public function isOverdue(): bool
    {
        return $this->expected_date && 
               $this->expected_date->isPast() && 
               !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Obtener estados disponibles
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_IN_TRANSIT => 'En Tránsito',
            self::STATUS_COMPLETED => 'Completada',
            self::STATUS_CANCELLED => 'Cancelada'
        ];
    }

    /**
     * Obtener nombre del estado
     */
    public function getStatusNameAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (empty($transfer->transfer_number)) {
                $transfer->transfer_number = self::generateTransferNumber();
            }
        });

        static::saved(function ($transfer) {
            $transfer->calculateTotals();
        });
    }
}