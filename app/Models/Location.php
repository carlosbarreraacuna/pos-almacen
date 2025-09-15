<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'parent_id',
        'warehouse_id',
        'aisle',
        'rack',
        'shelf',
        'bin',
        'capacity',
        'current_stock',
        'is_active',
        'temperature_controlled',
        'security_level'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'current_stock' => 'integer',
        'is_active' => 'boolean',
        'temperature_controlled' => 'boolean'
    ];

    // Tipos de ubicación
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_ZONE = 'zone';
    const TYPE_AISLE = 'aisle';
    const TYPE_RACK = 'rack';
    const TYPE_SHELF = 'shelf';
    const TYPE_BIN = 'bin';

    // Relaciones
    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Location::class, 'warehouse_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWarehouses($query)
    {
        return $query->where('type', self::TYPE_WAREHOUSE);
    }

    public function scopeAvailable($query)
    {
        return $query->whereColumn('current_stock', '<', 'capacity')
                    ->orWhereNull('capacity');
    }

    // Métodos auxiliares
    public function getFullCodeAttribute()
    {
        $parts = array_filter([
            $this->warehouse?->code,
            $this->aisle,
            $this->rack,
            $this->shelf,
            $this->bin
        ]);
        
        return implode('-', $parts) ?: $this->code;
    }

    public function getFullNameAttribute()
    {
        if ($this->parent) {
            return $this->parent->full_name . ' > ' . $this->name;
        }
        return $this->name;
    }

    public function getOccupancyPercentageAttribute()
    {
        if (!$this->capacity || $this->capacity == 0) {
            return 0;
        }
        
        return ($this->current_stock / $this->capacity) * 100;
    }

    public function getAvailableCapacityAttribute()
    {
        if (!$this->capacity) {
            return null;
        }
        
        return $this->capacity - $this->current_stock;
    }

    public function isWarehouse()
    {
        return $this->type === self::TYPE_WAREHOUSE;
    }

    public function isFull()
    {
        return $this->capacity && $this->current_stock >= $this->capacity;
    }

    public function canAccommodate($quantity)
    {
        if (!$this->capacity) {
            return true;
        }
        
        return ($this->current_stock + $quantity) <= $this->capacity;
    }

    public function updateStock($quantity, $operation = 'add')
    {
        if ($operation === 'add') {
            $this->current_stock += $quantity;
        } elseif ($operation === 'subtract') {
            $this->current_stock -= $quantity;
        } elseif ($operation === 'set') {
            $this->current_stock = $quantity;
        }
        
        $this->current_stock = max(0, $this->current_stock);
        $this->save();
    }

    public static function getLocationTypes()
    {
        return [
            self::TYPE_WAREHOUSE => 'Almacén',
            self::TYPE_ZONE => 'Zona',
            self::TYPE_AISLE => 'Pasillo',
            self::TYPE_RACK => 'Estante',
            self::TYPE_SHELF => 'Estantería',
            self::TYPE_BIN => 'Contenedor'
        ];
    }
}