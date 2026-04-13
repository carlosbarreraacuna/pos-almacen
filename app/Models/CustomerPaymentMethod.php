<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentMethod extends Model
{
    protected $fillable = [
        'customer_id', 'card_brand', 'last_four',
        'holder_name', 'exp_month', 'exp_year',
        'token', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
