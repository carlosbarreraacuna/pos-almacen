<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'DESCUENTO10',
                'name' => 'Descuento 10%',
                'description' => 'Descuento del 10% en toda la compra',
                'type' => 'percentage',
                'value' => 10,
                'min_purchase' => 50000,
                'max_discount' => 50000,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addMonths(3),
                'usage_limit' => 100,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'BIENVENIDA20',
                'name' => 'Bienvenida 20%',
                'description' => 'Descuento del 20% para nuevos clientes',
                'type' => 'percentage',
                'value' => 20,
                'min_purchase' => 100000,
                'max_discount' => 100000,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addMonths(6),
                'usage_limit' => 50,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'FIJO15000',
                'name' => 'Descuento Fijo $15.000',
                'description' => 'Descuento de $15.000 en tu compra',
                'type' => 'fixed',
                'value' => 15000,
                'min_purchase' => 80000,
                'max_discount' => null,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addMonths(2),
                'usage_limit' => 200,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'ENVIOGRATIS',
                'name' => 'Envío Gratis',
                'description' => 'Descuento equivalente al costo de envío',
                'type' => 'fixed',
                'value' => 15000,
                'min_purchase' => 0,
                'max_discount' => null,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addMonths(1),
                'usage_limit' => 500,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'MEGA30',
                'name' => 'Mega Descuento 30%',
                'description' => 'Descuento del 30% en compras mayores a $200.000',
                'type' => 'percentage',
                'value' => 30,
                'min_purchase' => 200000,
                'max_discount' => 150000,
                'valid_from' => Carbon::now(),
                'valid_until' => Carbon::now()->addWeeks(2),
                'usage_limit' => 30,
                'used_count' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $couponData) {
            Coupon::create($couponData);
        }
    }
}
