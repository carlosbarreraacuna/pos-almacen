<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'user_id',
        'frequency_count',
        'average_quantity',
        'last_price',
        'average_price',
        'last_sale_date',
        'first_sale_date',
        'days_between_purchases',
        'seasonal_data',
        'total_revenue'
    ];

    protected $casts = [
        'average_quantity' => 'decimal:2',
        'last_price' => 'decimal:2',
        'average_price' => 'decimal:2',
        'last_sale_date' => 'datetime',
        'first_sale_date' => 'datetime',
        'seasonal_data' => 'array',
        'total_revenue' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con el cliente
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para productos más frecuentes de un cliente
     */
    public function scopeMostFrequentForCustomer($query, $customerId, $limit = 10)
    {
        return $query->where('customer_id', $customerId)
            ->orderBy('frequency_count', 'desc')
            ->limit($limit);
    }

    /**
     * Scope para productos recientes de un cliente
     */
    public function scopeRecentForCustomer($query, $customerId, $days = 30)
    {
        return $query->where('customer_id', $customerId)
            ->where('last_sale_date', '>=', Carbon::now()->subDays($days))
            ->orderBy('last_sale_date', 'desc');
    }

    /**
     * Scope para productos con patrón de compra regular
     */
    public function scopeRegularPurchases($query, $customerId)
    {
        return $query->where('customer_id', $customerId)
            ->whereNotNull('days_between_purchases')
            ->where('frequency_count', '>=', 3)
            ->orderBy('frequency_count', 'desc');
    }

    /**
     * Actualiza el historial cuando se realiza una nueva venta
     */
    public static function updateFromSale(Sale $sale)
    {
        foreach ($sale->saleItems as $item) {
            self::updateOrCreateHistory(
                $sale->customer_id,
                $item->product_id,
                $sale->user_id,
                $item->quantity,
                $item->unit_price,
                $sale->sale_date
            );
        }
    }

    /**
     * Actualiza o crea un registro de historial
     */
    public static function updateOrCreateHistory($customerId, $productId, $userId, $quantity, $price, $saleDate)
    {
        $history = self::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->first();

        if ($history) {
            // Actualizar registro existente
            $history->updateHistory($quantity, $price, $saleDate);
        } else {
            // Crear nuevo registro
            self::create([
                'customer_id' => $customerId,
                'product_id' => $productId,
                'user_id' => $userId,
                'frequency_count' => 1,
                'average_quantity' => $quantity,
                'last_price' => $price,
                'average_price' => $price,
                'last_sale_date' => $saleDate,
                'first_sale_date' => $saleDate,
                'total_revenue' => $quantity * $price
            ]);
        }
    }

    /**
     * Actualiza el historial con una nueva venta
     */
    public function updateHistory($quantity, $price, $saleDate)
    {
        $newFrequency = $this->frequency_count + 1;
        $newAverageQuantity = (($this->average_quantity * $this->frequency_count) + $quantity) / $newFrequency;
        $newAveragePrice = (($this->average_price * $this->frequency_count) + $price) / $newFrequency;
        $newTotalRevenue = $this->total_revenue + ($quantity * $price);
        
        // Calcular días entre compras
        $daysBetween = null;
        if ($this->last_sale_date) {
            $daysBetween = Carbon::parse($saleDate)->diffInDays($this->last_sale_date);
            
            if ($this->days_between_purchases) {
                // Promedio de días entre compras
                $daysBetween = (($this->days_between_purchases * ($this->frequency_count - 1)) + $daysBetween) / $this->frequency_count;
            }
        }
        
        // Actualizar datos estacionales
        $seasonalData = $this->seasonal_data ?? [];
        $month = Carbon::parse($saleDate)->month;
        $seasonalData[$month] = ($seasonalData[$month] ?? 0) + 1;
        
        $this->update([
            'frequency_count' => $newFrequency,
            'average_quantity' => $newAverageQuantity,
            'last_price' => $price,
            'average_price' => $newAveragePrice,
            'last_sale_date' => $saleDate,
            'days_between_purchases' => $daysBetween,
            'seasonal_data' => $seasonalData,
            'total_revenue' => $newTotalRevenue
        ]);
    }

    /**
     * Obtiene recomendaciones de productos para un cliente
     */
    public static function getRecommendationsForCustomer($customerId, $limit = 5)
    {
        return self::with('product')
            ->where('customer_id', $customerId)
            ->where('frequency_count', '>=', 2)
            ->orderByRaw('frequency_count * (1 / GREATEST(EXTRACT(DAY FROM (NOW() - last_sale_date)), 1)) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtiene productos que el cliente podría necesitar pronto
     */
    public static function getUpcomingNeedsForCustomer($customerId)
    {
        $today = Carbon::now();
        
        return self::with('product')
            ->where('customer_id', $customerId)
            ->whereNotNull('days_between_purchases')
            ->where('frequency_count', '>=', 3)
            ->get()
            ->filter(function ($history) use ($today) {
                $expectedNextPurchase = Carbon::parse($history->last_sale_date)
                    ->addDays($history->days_between_purchases);
                
                // Productos que deberían comprarse en los próximos 7 días
                return $expectedNextPurchase->between($today, $today->copy()->addDays(7));
            })
            ->sortBy(function ($history) use ($today) {
                $expectedNextPurchase = Carbon::parse($history->last_sale_date)
                    ->addDays($history->days_between_purchases);
                return $expectedNextPurchase->diffInDays($today);
            });
    }

    /**
     * Obtiene estadísticas de compra del cliente
     */
    public static function getCustomerStats($customerId)
    {
        $histories = self::where('customer_id', $customerId)->get();
        
        return [
            'total_products' => $histories->count(),
            'total_revenue' => $histories->sum('total_revenue'),
            'average_frequency' => $histories->avg('frequency_count'),
            'most_bought_product' => $histories->sortByDesc('frequency_count')->first(),
            'highest_revenue_product' => $histories->sortByDesc('total_revenue')->first(),
            'seasonal_preferences' => $this->getSeasonalPreferences($histories)
        ];
    }

    /**
     * Obtiene preferencias estacionales del cliente
     */
    private static function getSeasonalPreferences($histories)
    {
        $monthlyData = [];
        
        foreach ($histories as $history) {
            if ($history->seasonal_data) {
                foreach ($history->seasonal_data as $month => $count) {
                    $monthlyData[$month] = ($monthlyData[$month] ?? 0) + $count;
                }
            }
        }
        
        arsort($monthlyData);
        
        return $monthlyData;
    }

    /**
     * Obtiene la cantidad sugerida para un producto
     */
    public function getSuggestedQuantityAttribute()
    {
        // Ajustar cantidad basada en frecuencia y tiempo transcurrido
        $daysSinceLastPurchase = Carbon::now()->diffInDays($this->last_sale_date);
        
        if ($this->days_between_purchases && $daysSinceLastPurchase > $this->days_between_purchases) {
            // Si ha pasado más tiempo del normal, sugerir cantidad mayor
            return ceil($this->average_quantity * 1.2);
        }
        
        return ceil($this->average_quantity);
    }

    /**
     * Verifica si es tiempo de recompra
     */
    public function getIsTimeToRepurchaseAttribute()
    {
        if (!$this->days_between_purchases || $this->frequency_count < 3) {
            return false;
        }
        
        $expectedNextPurchase = Carbon::parse($this->last_sale_date)
            ->addDays($this->days_between_purchases);
        
        return Carbon::now()->gte($expectedNextPurchase);
    }
}