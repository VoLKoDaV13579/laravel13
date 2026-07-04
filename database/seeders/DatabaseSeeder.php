<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => bcrypt('pass')]);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com', 'password' => bcrypt('pass')]);
        $charlie = User::create(['name' => 'Charlie', 'email' => 'charlie@test.com', 'password' => bcrypt('pass')]);
        $diana = User::create(['name' => 'Diana', 'email' => 'diana@test.com', 'password' => bcrypt('pass')]);
        $eve = User::create(['name' => 'Eve', 'email' => 'eve@test.com', 'password' => bcrypt('pass')]);

        $laptop = Product::create(['name' => 'Laptop', 'price' => 120000, 'stock' => 50]);
        $mouse = Product::create(['name' => 'Mouse', 'price' => 3000, 'stock' => 200]);
        $keyboard = Product::create(['name' => 'Keyboard', 'price' => 7000, 'stock' => 150]);
        $monitor = Product::create(['name' => 'Monitor', 'price' => 45000, 'stock' => 30]);
        $headphones = Product::create(['name' => 'Headphones', 'price' => 15000, 'stock' => 80]);
        $webcam = Product::create(['name' => 'Webcam', 'price' => 8000, 'stock' => 60]);

        $save10 = Coupon::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'min_order_total' => 5000, 'max_uses' => 100, 'times_used' => 15]);
        $flat500 = Coupon::create(['code' => 'FLAT500', 'type' => 'fixed', 'value' => 500, 'min_order_total' => 1000, 'max_uses' => 50, 'times_used' => 8]);

        // Alice: 3 orders
        $o1 = Order::create(['user_id' => $alice->id, 'status' => 'delivered', 'subtotal' => 243000, 'discount' => 24300, 'total' => 218700, 'coupon_id' => $save10->id, 'created_at' => '2026-06-01']);
        OrderItem::create(['order_id' => $o1->id, 'product_id' => $laptop->id, 'quantity' => 2, 'price' => 120000]);
        OrderItem::create(['order_id' => $o1->id, 'product_id' => $mouse->id, 'quantity' => 1, 'price' => 3000]);

        $o2 = Order::create(['user_id' => $alice->id, 'status' => 'confirmed', 'subtotal' => 3000, 'discount' => 0, 'total' => 3000, 'created_at' => '2026-06-20']);
        OrderItem::create(['order_id' => $o2->id, 'product_id' => $mouse->id, 'quantity' => 1, 'price' => 3000]);

        $o3 = Order::create(['user_id' => $alice->id, 'status' => 'pending', 'subtotal' => 45000, 'discount' => 0, 'total' => 45000, 'created_at' => '2026-07-01']);
        OrderItem::create(['order_id' => $o3->id, 'product_id' => $monitor->id, 'quantity' => 1, 'price' => 45000]);

        // Bob: 3 orders
        $o4 = Order::create(['user_id' => $bob->id, 'status' => 'delivered', 'subtotal' => 15000, 'discount' => 1500, 'total' => 13500, 'coupon_id' => $save10->id, 'created_at' => '2026-06-05']);
        OrderItem::create(['order_id' => $o4->id, 'product_id' => $headphones->id, 'quantity' => 1, 'price' => 15000]);

        $o5 = Order::create(['user_id' => $bob->id, 'status' => 'delivered', 'subtotal' => 7000, 'discount' => 500, 'total' => 6500, 'coupon_id' => $flat500->id, 'created_at' => '2026-06-15']);
        OrderItem::create(['order_id' => $o5->id, 'product_id' => $keyboard->id, 'quantity' => 1, 'price' => 7000]);

        $o6 = Order::create(['user_id' => $bob->id, 'status' => 'cancelled', 'subtotal' => 120000, 'discount' => 0, 'total' => 120000, 'created_at' => '2026-06-25']);
        OrderItem::create(['order_id' => $o6->id, 'product_id' => $laptop->id, 'quantity' => 1, 'price' => 120000]);

        // Charlie: 2 orders
        $o7 = Order::create(['user_id' => $charlie->id, 'status' => 'delivered', 'subtotal' => 52000, 'discount' => 5200, 'total' => 46800, 'coupon_id' => $save10->id, 'created_at' => '2026-06-10']);
        OrderItem::create(['order_id' => $o7->id, 'product_id' => $monitor->id, 'quantity' => 1, 'price' => 45000]);
        OrderItem::create(['order_id' => $o7->id, 'product_id' => $keyboard->id, 'quantity' => 1, 'price' => 7000]);

        $o8 = Order::create(['user_id' => $charlie->id, 'status' => 'delivered', 'subtotal' => 3000, 'discount' => 0, 'total' => 3000, 'created_at' => '2026-06-28']);
        OrderItem::create(['order_id' => $o8->id, 'product_id' => $mouse->id, 'quantity' => 1, 'price' => 3000]);

        // Diana: 1 order
        $o9 = Order::create(['user_id' => $diana->id, 'status' => 'confirmed', 'subtotal' => 120000, 'discount' => 12000, 'total' => 108000, 'coupon_id' => $save10->id, 'created_at' => '2026-07-02']);
        OrderItem::create(['order_id' => $o9->id, 'product_id' => $laptop->id, 'quantity' => 1, 'price' => 120000]);

        // Eve: 1 order
        $o10 = Order::create(['user_id' => $eve->id, 'status' => 'pending', 'subtotal' => 8000, 'discount' => 500, 'total' => 7500, 'coupon_id' => $flat500->id, 'created_at' => '2026-07-03']);
        OrderItem::create(['order_id' => $o10->id, 'product_id' => $webcam->id, 'quantity' => 1, 'price' => 8000]);
    }
}
