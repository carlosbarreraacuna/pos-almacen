<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'manager_name',
        'is_active',
        'is_main',
        'capacity',
        'current_utilization'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'capacity' => 'decimal:2',
        'current_utilization' => 'decimal:2',
        'operating_hours' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Relación con ubicaciones
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Relación con transferencias de origen
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    /**
     * Relación con transferencias de destino
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    /**
     * Relación con ajustes de inventario
     */
    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /**
     * Scope para almacenes activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar almacenes
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%");
        });
    }

    /**
     * Obtener almacén principal
     */
    public static function getMainWarehouse()
    {
        return static::where('is_main', true)->first();
    }

    /**
     * Establecer como almacén principal
     */
    public function setAsMain(): bool
    {
        // Quitar el estado principal de otros almacenes
        static::where('is_main', true)->update(['is_main' => false]);
        
        // Establecer este como principal
        $this->is_main = true;
        return $this->save();
    }

    /**
     * Obtener stock total del almacén
     */
    public function getTotalStockAttribute(): int
    {
        return $this->locations()
            ->join('products', 'locations.id', '=', 'products.location_id')
            ->sum('products.stock');
    }

    /**
     * Obtener número de productos únicos
     */
    public function getUniqueProductsCountAttribute(): int
    {
        return $this->locations()
            ->join('products', 'locations.id', '=', 'products.location_id')
            ->distinct('products.id')
            ->count('products.id');
    }

    /**
     * Obtener porcentaje de utilización
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->capacity == 0) {
            return 0;
        }
        return ($this->current_utilization / $this->capacity) * 100;
    }

    /**
     * Verificar si el almacén tiene capacidad disponible
     */
    public function hasAvailableCapacity(float $requiredSpace = 0): bool
    {
        $availableCapacity = $this->capacity - $this->current_utilization;
        return $availableCapacity >= $requiredSpace;
    }

    /**
     * Actualizar utilización actual
     */
    public function updateUtilization(): void
    {
        // Calcular utilización basada en productos almacenados
        $totalProducts = $this->totalStock;
        $this->current_utilization = $totalProducts * 0.1; // Asumiendo 0.1 m³ por producto
        $this->save();
    }

    /**
     * Obtener estadísticas del almacén
     */
    public function getStats(): array
    {
        return [
            'total_locations' => $this->locations()->count(),
            'active_locations' => $this->locations()->active()->count(),
            'total_stock' => $this->totalStock,
            'unique_products' => $this->uniqueProductsCount,
            'utilization_percentage' => $this->utilizationPercentage,
            'available_capacity' => $this->capacity - $this->current_utilization,
            'pending_transfers_in' => $this->incomingTransfers()->pending()->count(),
            'pending_transfers_out' => $this->outgoingTransfers()->pending()->count()
        ];
    }

    /**
     * Verificar si se puede eliminar el almacén
     */
    public function canBeDeleted(): bool
    {
        // No se puede eliminar si es el almacén principal
        if ($this->is_main) {
            return false;
        }

        // No se puede eliminar si tiene stock
        if ($this->totalStock > 0) {
            return false;
        }

        // No se puede eliminar si tiene transferencias pendientes
        if ($this->incomingTransfers()->pending()->exists() || 
            $this->outgoingTransfers()->pending()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Obtener dirección completa
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ]);

        return implode(', ', $parts);
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($warehouse) {
            // Generar código automático si no se proporciona
            if (empty($warehouse->code)) {
                $warehouse->code = 'WH' . str_pad(static::count() + 1, 3, '0', STR_PAD_LEFT);
            }
        });

        static::deleting(function ($warehouse) {
            if (!$warehouse->canBeDeleted()) {
                throw new \Exception('No se puede eliminar el almacén porque tiene stock o transferencias pendientes.');
            }
        });
    }
}