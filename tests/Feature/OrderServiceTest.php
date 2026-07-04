<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCreatedNotification;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_order_with_one_item(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2]
        ]);
        Notification::assertSentTo($user, OrderCreatedNotification::class);
        $this->assertSame('pending', $order->status);
        $this->assertSame(2000, $order->subtotal);
        $this->assertSame(2000, $order->total);
        $this->assertCount(1, $order->items);
        $this->assertSame(8, $product->fresh()->stock);
    }

    #[Test]
    public function create_order_with_multiple_items(): void
    {
        $user = User::factory()->create();
        $productOne = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $productTwo = Product::factory()->create(['price' => 2000, 'stock' => 10]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $productOne->id, 'quantity' => 2],
            ['product_id' => $productTwo->id, 'quantity' => 4]
        ]);
        $this->assertSame('pending', $order->status);
        $this->assertSame(10000, $order->subtotal);
        $this->assertSame(10000, $order->total);
        $this->assertCount(2, $order->items);
        $this->assertSame(8, $productOne->fresh()->stock);
        $this->assertSame(6, $productTwo->fresh()->stock);
    }

    #[Test]
    public function create_order_with_empty_items_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must contain at least one item.');

        $user = User::factory()->create();
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $service->createOrder($user, []);
    }

    #[Test]
    public function create_order_with_items_out_of_stock_throws_exception(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 0]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Product \"{$product->name}\" is out of stock.");
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2]
        ]);
    }


    #[Test]
    public function create_order_with_coupon_not_found_throws_exception(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $couponCode = '123';
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Coupon \"{$couponCode}\" not found.");
        $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2]
        ], $couponCode);
    }

    #[Test]
    public function create_order_with_not_valid_coupon_throws_exception(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $coupon = Coupon::factory()->create([
            'code' => '0000000',
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 1000,
            'max_uses' => 1,
            'times_used' => 1,
            'expires_at' => Carbon::yesterday()
        ]);
        $couponCode = $coupon->code;
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Coupon \"{$couponCode}\" is expired or fully used.");
        $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2]
        ], $couponCode);
    }

    #[Test]
    public function create_order_with_coupon(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $coupon = Coupon::factory()->create([
            'code' => '0000000',
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 1000,
            'max_uses' => 1,
            'times_used' => 0,
            'expires_at' => Carbon::tomorrow()
        ]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ], $coupon->code);
        $this->assertSame('pending', $order->status);
        $this->assertSame(2000, $order->subtotal);
        $this->assertSame(500, $order->discount);
        $this->assertSame(1500, $order->total);
        $this->assertCount(1, $order->items);
        $this->assertSame(1, $coupon->fresh()->times_used);
        $this->assertSame(8, $product->fresh()->stock);
    }

    #[Test]
    public function confirm_order()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->once()
            ->with(2000, Mockery::type('string'))
            ->andReturn('txn_123');
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);
        $service->confirmOrder($order);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    #[Test]
    public function confirm_order_with_wrong_status_throws_exception(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->once()
            ->with(2000, Mockery::type('string'))
            ->andReturn('txn_123');
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);
        $service->confirmOrder($order);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Only pending orders can be confirmed. Current status: confirmed.");
        $service->confirmOrder($order);
    }

    #[Test]
    public function deliver_order()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->once()
            ->with(2000, Mockery::type('string'))
            ->andReturn('txn_123');
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);
        $service->confirmOrder($order);
        $service->deliverOrder($order);
        $this->assertSame('delivered', $order->fresh()->status);
    }

    #[Test]
    public function deliver_order_with_wrong_status_throws_exception(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Only confirmed orders can be delivered. Current status: {$order->status}.");
        $service->deliverOrder($order);
    }

    #[Test]
    public function cancel_order(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $coupon = Coupon::factory()->create([
            'code' => '0000000',
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 1000,
            'max_uses' => 1,
            'times_used' => 0,
            'expires_at' => Carbon::tomorrow()
        ]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway
            ->shouldReceive('charge')
            ->once()
            ->with(1500, Mockery::type('string'))
            ->andReturn('txn_123');
        $gateway->shouldReceive('refund')
            ->once()
            ->with('txn_123');
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ], $coupon->code);
        Notification::assertSentTo($user, OrderCreatedNotification::class);
        $service->confirmOrder($order);
        $service->cancelOrder($order);
        Notification::assertSentTo($user, OrderCancelledNotification::class);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->times_used);
    }

    #[Test]
    public function cancel_order_with_wrong_status_throws_exception(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->once()
            ->with(2000, Mockery::type('string'))
            ->andReturn('txn_123');
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);
        $service->confirmOrder($order);
        $service->deliverOrder($order);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Order cannot be cancelled. Current status: {$order->status}.");
        $service->cancelOrder($order);
    }

    #[Test]
    public function recalculate_order_total(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $coupon = Coupon::factory()->create([
            'code' => '0000000',
            'type' => 'fixed',
            'value' => 500,
            'min_order_total' => 1000,
            'max_uses' => 1,
            'times_used' => 0,
            'expires_at' => Carbon::tomorrow()
        ]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = $service->createOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ], $coupon->code);
        $order->total = 100000;
        $order->discount = 1000;
        $order->subtotal = 10000;
        $order->save();
        $service->recalculateTotal($order);
        $this->assertSame(1500, $order->fresh()->total);
        $this->assertSame(500, $order->fresh()->discount);
        $this->assertSame(2000, $order->fresh()->subtotal);
    }

    #[Test]
    #[DataProvider('invalidStatusesForConfirm')]
    public function confirm_order_rejects_invalid_status(string $status): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = Order::factory()->create(['status' => $status]);

        $this->expectException(\LogicException::class);
        $service->confirmOrder($order);
    }

    public static function invalidStatusesForConfirm(): array
    {
        return [
            'confirmed' => ['confirmed'],
            'delivered' => ['delivered'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[Test]
    #[DataProvider('InvalidStatusesForDelivery')]
    public function delivery_order_rejects_invalid_status(string $status): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = Order::factory()->create(['status' => $status]);
        $this->expectException(\LogicException::class);
        $service->deliverOrder($order);
    }

    public static function InvalidStatusesForDelivery(): array
    {
        return [
            'delivered' => ['delivered'],
            'cancelled' => ['cancelled'],
            'pending' => ['pending'],
        ];
    }

    #[Test]
    #[DataProvider('invalidStatusesForCancel')]
    public function cancel_order_rejects_invalid_status(string $status): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);
        $order = Order::factory()->create(['status' => $status]);
        $this->expectException(\LogicException::class);
        $service->cancelOrder($order);

    }

    public static function invalidStatusesForCancel(): array
    {
        return [
            'delivered' => ['delivered'],
        ];
    }


    #[Test]
    public function create_order_rolls_back_on_failure(): void
    {
        $user = User::factory()->create();
        $productOne = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $productTwo = Product::factory()->create(['price' => 1000, 'stock' => 0]);
        $gateway = Mockery::mock(PaymentGateway::class);
        $service = new OrderService($gateway);

        try {
            $order = $service->createOrder($user, [
                ['product_id' => $productOne->id, 'quantity' => 2],
                ['product_id' => $productTwo->id, 'quantity' => 15],
            ]);
        } catch (\RuntimeException) {

        }

        $this->assertDatabaseHas('products', [
            'id' => $productOne->id,
            'stock' => 10,
        ]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }
}
