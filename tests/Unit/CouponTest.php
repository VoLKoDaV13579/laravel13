<?php

namespace Tests\Unit;

use App\Models\Coupon;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CouponTest extends TestCase
{

    private function makeCoupon(array $attributes = []): Coupon
    {
        $coupon = new Coupon();
        foreach ($attributes as $attribute => $value) {
            $coupon->{$attribute} = $value;
        }
        return $coupon;
    }

    #[Test]
    public function valid_when_no_expiry_and_no_usage(): void
    {
        $coupon = $this->makeCoupon([
            'expires_at' => null,
            'max_uses' => null,
            "times_used" => 0,
        ]);
        $this->assertTrue($coupon->isValid());
    }

    #[Test]
    public function invalid_when_expired(): void
    {
        $coupon = $this->makeCoupon([
            'expires_at' => Carbon::yesterday(),
            'max_uses' => null,
            "times_used" => 0,
        ]);
        $this->assertFalse($coupon->isValid());
    }

    #[Test]
    public function valid_when_expire_in_the_future(): void
    {
        $coupon = $this->makeCoupon([
            'expires_at' => Carbon::tomorrow(),
            'max_uses' => null,
            "times_used" => 0,
        ]);
        $this->assertTrue($coupon->isValid());
    }

    #[Test]
    public function invalid_when_usage_limit_reached(): void
    {
        $coupon = $this->makeCoupon([
            'expires_at' => Carbon::tomorrow(),
            'max_uses' => 5,
            "times_used" => 5,
        ]);
        $this->assertFalse($coupon->isValid());
    }

    #[Test]
    public function valid_when_uses_below_limit(): void
    {
        $coupon = $this->makeCoupon([
            'expires_at' => Carbon::tomorrow(),
            'max_uses' => 10,
            "times_used" => 3,
        ]);
        $this->assertTrue($coupon->isValid());
    }

    #[Test]
    public function invalid_when_both_expired_and_limit_reached(): void
    {
        $coupon = $this->makeCoupon([
            'expires_at' => Carbon::yesterday(),
            'max_uses' => 5,
            "times_used" => 5,
        ]);
        $this->assertFalse($coupon->isValid());
    }

    #[Test]
    public function return_zero_when_subtotal_below_minimum(): void
    {
        $coupon = $this->makeCoupon([
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 3000,
        ]);
        $this->assertSame(0, $coupon->calculateDiscount(2000));
    }

    #[Test]
    public function fixed_discount_applied_fully(): void
    {
        $coupon = $this->makeCoupon([
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 1000,
        ]);
        $this->assertSame(500, $coupon->calculateDiscount(5000));
    }

    #[Test]
    public function fixed_discount_capped_at_subtotal(): void
    {
        $coupon = $this->makeCoupon([
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 0,
        ]);
        $this->assertSame(300, $coupon->calculateDiscount(300));
    }

    #[Test]
    public function percent_discount_calculated_correctly(): void
    {
        $coupon = $this->makeCoupon([
            'type' => 'percent',
            'value' => 10,
            'min_order_total' => 0,
        ]);
        $this->assertSame(500, $coupon->calculateDiscount(5000));
    }

    #[Test]
    public function percent_discount_floors_fractions_result(): void
    {
        $coupon = $this->makeCoupon([
            'type' => 'percent',
            'value' => 15,
            'min_order_total' => 0,
        ]);
        $this->assertSame(4, $coupon->calculateDiscount(33));
    }

    #[Test]
    public function discount_applied_when_subtotal_equals_minimum(): void
    {
        $coupon = $this->makeCoupon([
            'type' => 'fixed',
            'value' => 200,
            'min_order_total' => 1000,
        ]);
        $this->assertSame(200, $coupon->calculateDiscount(1000));
    }

    #[Test]
    public function percent_100_returns_full_subtotal(): void
    {
        $coupon = $this->makeCoupon([
            'type' => 'percent',
            'value' => 100,
            'min_order_total' => 0,
        ]);
        $this->assertSame(4000, $coupon->calculateDiscount(4000));
    }
}
