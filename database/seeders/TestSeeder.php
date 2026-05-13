<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\Earning;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestSeeder extends Seeder
{
    public function run(): void
    {
        $city = City::first();
        $category = Category::first();

        // ─── Seller 1 ─────────────────────────────────────────
        $seller1 = User::create([
            'first_name' => 'Youssef',
            'last_name' => 'Alami',
            'email' => 'aa@aa.com',
            'phone_number' => '0600000001',
            'password' => Hash::make('aaaaaaaa'),
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $store1 = Store::create([
            'user_id' => $seller1->id,
            'category_id' => $category->id,
            'city_id' => $city->id,
            'store_name' => 'Atelier Alami',
            'slug' => 'atelier-alami',
            'description' => 'Poterie artisanale de Fès.',
        ]);

        $product1 = Product::create([
            'store_id' => $store1->id,
            'category_id' => $category->id,
            'name' => 'Vase en céramique bleue',
            'slug' => 'vase-ceramique-bleue-'.Str::random(5),
            'description' => 'Vase fait main, céramique de Fès.',
            'base_price' => 150.00,
            'sale_price' => null,
            'stock' => 10,
            'status' => 'published',
        ]);

        // ─── Seller 2 ─────────────────────────────────────────
        $seller2 = User::create([
            'first_name' => 'Fatima',
            'last_name' => 'Benali',
            'email' => 'bb@bb.com',
            'phone_number' => '0600000002',
            'password' => Hash::make('aaaaaaaa'),
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $store2 = Store::create([
            'user_id' => $seller2->id,
            'category_id' => $category->id,
            'city_id' => $city->id,
            'store_name' => 'Maison Benali',
            'slug' => 'maison-benali',
            'description' => 'Maroquinerie artisanale de Marrakech.',
        ]);

        $product2 = Product::create([
            'store_id' => $store2->id,
            'category_id' => $category->id,
            'name' => 'Sac en cuir naturel',
            'slug' => 'sac-cuir-naturel-'.Str::random(5),
            'description' => 'Sac à main en cuir tanné naturellement.',
            'base_price' => 320.00,
            'sale_price' => 280.00,
            'stock' => 5,
            'status' => 'published',
        ]);

        // ─── Buyer ────────────────────────────────────────────
        $buyer = User::create([
            'first_name' => 'Karim',
            'last_name' => 'Mansouri',
            'email' => 'cc@cc.com',
            'phone_number' => '0600000003',
            'password' => Hash::make('aaaaaaaa'),
            'role' => 'buyer',
            'email_verified_at' => now(),
        ]);

        // ─── Order from buyer → Seller 1 ──────────────────────
        $order1 = Order::create([
            'buyer_id' => $buyer->id,
            'store_id' => $store1->id,
            'city_id' => $city->id,
            'tracking_number' => null,
            'status' => 'pending',
            'total_amount' => 150.00,
            'platform_fee' => 7.50,   // 5%
            'amana_fee' => 15.00,
            'seller_earnings' => 127.50,
            'shipping_address' => '12 Rue Ibn Khaldoun, Fès',
            'notes' => null,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $product1->id,
            'quantity' => 1,
            'unit_price' => 150.00,
            'subtotal' => 150.00,
        ]);

        Earning::create([
            'store_id' => $store1->id,
            'order_id' => $order1->id,
            'gross_amount' => 150.00,
            'platform_fee' => 7.50,
            'amana_fee' => 15.00,
            'net_amount' => 127.50,
            'status' => 'pending',
        ]);

        // ─── Order from buyer → Seller 2 ──────────────────────
        $order2 = Order::create([
            'buyer_id' => $buyer->id,
            'store_id' => $store2->id,
            'city_id' => $city->id,
            'tracking_number' => null,
            'status' => 'pending',
            'total_amount' => 280.00,
            'platform_fee' => 14.00,  // 5%
            'amana_fee' => 15.00,
            'seller_earnings' => 251.00,
            'shipping_address' => '12 Rue Ibn Khaldoun, Fès',
            'notes' => null,
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => 280.00,
            'subtotal' => 280.00,
        ]);

        Earning::create([
            'store_id' => $store2->id,
            'order_id' => $order2->id,
            'gross_amount' => 280.00,
            'platform_fee' => 14.00,
            'amana_fee' => 15.00,
            'net_amount' => 251.00,
            'status' => 'pending',
        ]);

        $this->command->info('✅ Test data seeded:');
        $this->command->info('   seller1@hirfaty.test  →  Atelier Alami  →  1 order');
        $this->command->info('   seller2@hirfaty.test  →  Maison Benali  →  1 order');
        $this->command->info('   buyer@hirfaty.test    →  2 orders placed');
        $this->command->info('   Password for all: password');
    }
}
