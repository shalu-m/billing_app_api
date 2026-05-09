<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('products')->truncate();

        $products = [
            [
                'name'          => 'Premium Basmati Rice',
                'unit'          => 'kg',
                'cost_price'    => 85.00,
                'selling_price' => 110.00,
                'sgst'          => 5.00,
                'cgst'          => 2.00,
                'stock'         => 225,
                'barcode'       => '8901234567890',
            ],
            [
                'name'          => 'Refined Sunflower Oil',
                'unit'          => 'Litre',
                'cost_price'    => 140.00,
                'selling_price' => 175.00,
                'sgst'          => 0.00,
                'cgst'          => 0.00,
                'stock'         => 50,
                'barcode'       => '8901234567891',
            ],
            [
                'name'          => 'Coffee Beans',
                'unit'          => 'Packet',
                'cost_price'    => 42.00,
                'selling_price' => 55.00,
                'sgst'          => 3.00,
                'cgst'          => 2.00,
                'stock'         => 150,
                'barcode'       => '8901234567892',
            ],
            [
                'name'          => 'Premium Brown Eggs (Dozen)',
                'unit'          => 'Dozen',
                'cost_price'    => 100.00,
                'selling_price' => 120.00,
                'sgst'          => 2.00,
                'cgst'          => 2.00,
                'stock'         => 200,
                'barcode'       => '8901234567893',
            ],
            [
                'name'          => 'Fresh Milk 1L Tetra',
                'unit'          => 'Litre',
                'cost_price'    => 52.00,
                'selling_price' => 65.00,
                'sgst'          => 1.50,
                'cgst'          => 1.50,
                'stock'         => 80,
                'barcode'       => '8901234567894',
            ],
            [
                'name'          => 'Whole Wheat Bread 400g',
                'unit'          => 'Packet',
                'cost_price'    => 40.00,
                'selling_price' => 50.00,
                'sgst'          => 2.00,
                'cgst'          => 2.00,
                'stock'         => 60,
                'barcode'       => '8901234567895',
            ],
            [
                'name'          => 'Toor Dal 1kg',
                'unit'          => 'kg',
                'cost_price'    => 110.00,
                'selling_price' => 135.00,
                'sgst'          => 5.00,
                'cgst'          => 5.00,
                'stock'         => 90,
                'barcode'       => '8901234567896',
            ],
            [
                'name'          => 'Coconut Oil 500ml',
                'unit'          => 'ml',
                'cost_price'    => 80.00,
                'selling_price' => 105.00,
                'sgst'          => 3.00,
                'cgst'          => 2.00,
                'stock'         => 40,
                'barcode'       => '8901234567897',
            ],
            [
                'name'          => 'Aashirvaad Atta 5kg',
                'unit'          => 'kg',
                'cost_price'    => 210.00,
                'selling_price' => 255.00,
                'sgst'          => 0.00,
                'cgst'          => 0.00,
                'stock'         => 35,
                'barcode'       => '8901234567898',
            ],
            [
                'name'          => 'Amul Butter 500g',
                'unit'          => 'Piece',
                'cost_price'    => 225.00,
                'selling_price' => 260.00,
                'sgst'          => 2.50,
                'cgst'          => 2.50,
                'stock'         => 25,
                'barcode'       => '8901234567899',
            ],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert(array_merge($product, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('✅ Products seeded: ' . count($products));
    }
}
