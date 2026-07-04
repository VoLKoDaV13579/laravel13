<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly PaymentGateway $paymentGateway)
    {
    }

    /**
     * @param array<array{product_id: int, quantity: int}> $items
     */
    public function createOrder(User $user, array $items, ?string $couponCode = null): Order
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('Order must contain at least one item.');
        }

        return DB::transaction(function () use ($user, $items, $couponCode) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
            ]);

            $subtotal = 0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if (!$product->isInStock($item['quantity'])) {
                    throw new \RuntimeException("Product \"{$product->name}\" is out of stock.");
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);

                $subtotal += $product->price * $item['quantity'];
            }

            $discount = 0;

            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->first();

                if (!$coupon) {
                    throw new \InvalidArgumentException("Coupon \"{$couponCode}\" not found.");
                }

                if (!$coupon->isValid()) {
                    throw new \InvalidArgumentException("Coupon \"{$couponCode}\" is expired or fully used.");
                }

                $discount = $coupon->calculateDiscount($subtotal);

                if ($discount > 0) {
                    $order->coupon_id = $coupon->id;
                    $coupon->increment('times_used');
                }
            }

            $order->update([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
            ]);

            $order = $order->fresh(['items']);

            $user->notify(new OrderCreatedNotification($order));

            return $order;
        });
    }

    public function confirmOrder(Order $order): Order
    {
        if ($order->status !== 'pending') {
            throw new \LogicException("Only pending orders can be confirmed. Current status: {$order->status}.");
        }

        $transactionId = $this->paymentGateway->charge(
            $order->total,
            "Payment for order #{$order->id}"
        );

        $order->update([
            'status' => 'confirmed',
            'transaction_id' => $transactionId,
        ]);

        return $order;
    }

    public function deliverOrder(Order $order): Order
    {
        if ($order->status !== 'confirmed') {
            throw new \LogicException("Only confirmed orders can be delivered. Current status: {$order->status}.");
        }

        $order->update(['status' => 'delivered']);

        return $order;
    }

    public function cancelOrder(Order $order): Order
    {
        if (!$order->isCancellable()) {
            throw new \LogicException("Order cannot be cancelled. Current status: {$order->status}.");
        }

        return DB::transaction(function () use ($order) {
            if ($order->transaction_id) {
                $this->paymentGateway->refund($order->transaction_id);
            }

            foreach ($order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }

            if ($order->coupon_id) {
                $order->coupon->decrement('times_used');
            }

            $order->update(['status' => 'cancelled']);

            $order->user->notify(new OrderCancelledNotification($order));

            return $order;
        });
    }

    public function recalculateTotal(Order $order): Order
    {
        $subtotal = $order->items->sum(fn ($item) => $item->getLineTotal());

        $discount = 0;
        if ($order->coupon) {
            $discount = $order->coupon->calculateDiscount($subtotal);
        }

        $order->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
        ]);

        return $order;
    }
}
