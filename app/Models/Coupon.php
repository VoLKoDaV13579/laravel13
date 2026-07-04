<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'type', 'value', 'min_order_total', 'max_uses', 'expires_at'])]
class Coupon extends Model
{
    use HasFactory;
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->times_used >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(int $subtotal): int
    {
        if ($subtotal < $this->min_order_total) {
            return 0;
        }

        return match ($this->type) {
            'fixed' => min($this->value, $subtotal),
            'percent' => (int) floor($subtotal * $this->value / 100),
        };
    }
}
