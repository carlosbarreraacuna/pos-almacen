<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'logo_url',
        'website',
        'contact_email',
        'contact_phone',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relaciones
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Métodos auxiliares
    public function getProductCountAttribute()
    {
        return $this->products()->count();
    }

    public function getTotalStockValueAttribute()
    {
        return $this->products()->sum(function ($product) {
            return $product->stock_quantity * $product->cost_price;
        });
    }
}