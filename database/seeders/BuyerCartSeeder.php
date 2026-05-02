<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BuyerCartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $city     = City::first();
        $category = Category::first();

        // ─── Seller 1 + 5 products ────────────────────────────
        $seller1 = User::firstOrCreate(
            ['email' => 'seller1@hirfaty.test'],
            [
                'first_name'        => 'Youssef',
                'last_name'         => 'Alami',
                'phone_number'             => '0600000001',
                'password'          => Hash::make('password'),
                'role'              => 'seller',
                'email_verified_at' => now(),
            ]
        );

        $store1 = Store::firstOrCreate(
            ['user_id' => $seller1->id],
            [
                'category_id' => $category->id,
                'city_id'     => $city->id,
                'store_name'  => 'Atelier Alami',
                'slug'        => 'atelier-alami',
                'description' => 'Poterie artisanale de Fès.',
            ]
        );

        $store1Products = [
            ['name' => 'Vase en céramique bleue',   'price' => 150.00, 'sale' => null],
            ['name' => 'Bol en terre cuite',         'price' => 80.00,  'sale' => 65.00],
            ['name' => 'Plat à tajine décoratif',    'price' => 220.00, 'sale' => null],
            ['name' => 'Assiette zellige bleue',     'price' => 95.00,  'sale' => 85.00],
            ['name' => 'Carafe en céramique peinte', 'price' => 175.00, 'sale' => null],
        ];

        $products1 = [];
        foreach ($store1Products as $p) {
            $products1[] = Product::create([
                'store_id'    => $store1->id,
                'category_id' => $category->id,
                'name'        => $p['name'],
                'slug'        => Str::slug($p['name']) . '-' . Str::random(5),
                'description' => "Produit artisanal fait main par {$store1->store_name}.",
                'base_price'  => $p['price'],
                'sale_price'  => $p['sale'],
                'stock'       => 10,
                'status'      => 'published',
            ]);
        }

        // ─── Seller 2 + 5 products ────────────────────────────
        $seller2 = User::firstOrCreate(
            ['email' => 'seller2@hirfaty.test'],
            [
                'first_name'        => 'Fatima',
                'last_name'         => 'Benali',
                'phone_number'             => '0600000002',
                'password'          => Hash::make('password'),
                'role'              => 'seller',
                'email_verified_at' => now(),
            ]
        );

        $store2 = Store::firstOrCreate(
            ['user_id' => $seller2->id],
            [
                'category_id' => $category->id,
                'city_id'     => $city->id,
                'store_name'  => 'Maison Benali',
                'slug'        => 'maison-benali',
                'description' => 'Maroquinerie artisanale de Marrakech.',
            ]
        );

        $store2Products = [
            ['name' => 'Sac en cuir naturel',          'price' => 320.00, 'sale' => 280.00],
            ['name' => 'Ceinture en cuir tanné',       'price' => 120.00, 'sale' => null],
            ['name' => 'Portefeuille artisanal',       'price' => 85.00,  'sale' => 70.00],
            ['name' => 'Babouches en cuir doré',       'price' => 195.00, 'sale' => null],
            ['name' => 'Pochette brodée traditionnelle','price' => 145.00, 'sale' => 125.00],
        ];

        $products2 = [];
        foreach ($store2Products as $p) {
            $products2[] = Product::create([
                'store_id'    => $store2->id,
                'category_id' => $category->id,
                'name'        => $p['name'],
                'slug'        => Str::slug($p['name']) . '-' . Str::random(5),
                'description' => "Produit artisanal fait main par {$store2->store_name}.",
                'base_price'  => $p['price'],
                'sale_price'  => $p['sale'],
                'stock'       => 10,
                'status'      => 'published',
            ]);
        }

        // ─── Buyer ────────────────────────────────────────────
        $buyer = User::firstOrCreate(
            ['email' => 'buyer1@hirfaty.test'],
            [
                'first_name'        => 'Karim',
                'last_name'         => 'Mansouri',
                'phone_number'             => '0600000003',
                'password'          => Hash::make('password'),
                'role'              => 'buyer',
                'email_verified_at' => now(),
            ]
        );

        // ─── Cart: 2 from store1, 3 from store2 ───────────────
        $cart = Cart::firstOrCreate(['user_id' => $buyer->id]);

        // 2 products from seller 1
        CartItem::firstOrCreate(['cart_id' => $cart->id, 'product_id' => $products1[0]->id], ['quantity' => 1]);
        CartItem::firstOrCreate(['cart_id' => $cart->id, 'product_id' => $products1[1]->id], ['quantity' => 2]);

        // 3 products from seller 2
        CartItem::firstOrCreate(['cart_id' => $cart->id, 'product_id' => $products2[0]->id], ['quantity' => 1]);
        CartItem::firstOrCreate(['cart_id' => $cart->id, 'product_id' => $products2[1]->id], ['quantity' => 1]);
        CartItem::firstOrCreate(['cart_id' => $cart->id, 'product_id' => $products2[2]->id], ['quantity' => 3]);

        $this->command->info('✅ Seeded:');
        $this->command->info("   seller1@hirfaty.test → Atelier Alami → 5 products");
        $this->command->info("   seller2@hirfaty.test → Maison Benali → 5 products");
        $this->command->info("   buyer@hirfaty.test   → cart with 5 items (2 + 3)");
        $this->command->info("   Password for all: password");

    }
}
