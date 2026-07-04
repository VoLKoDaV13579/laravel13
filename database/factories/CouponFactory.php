<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??????')),
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 0,
            'max_uses' => null,
            'expires_at' => null,
        ];
    }

    public function percent(int $value = 10): static
    {
        return $this->state([
            'type' => 'percent',
            'value' => $value,
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state([
            'max_uses' => 1,
            'times_used' => 1,
        ]);
    }

    public function withMinTotal(int $amount): static
    {
        return $this->state([
            'min_order_total' => $amount,
        ]);
    }
}
