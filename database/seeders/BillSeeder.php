<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('bill_items')->truncate();
        DB::table('bills')->truncate();

        $bills = [
            [
                'bill_number'     => 'B-2025-001',
                'customer_name'   => 'Ragul',
                'payment_method'  => 'Cash',
                'subtotal'        => 510.00,
                'total_discount'  => 0.00,
                'total_sgst'      => 4.80,
                'total_cgst'      => 4.80,
                'grand_total'     => 519.60,
                'amount_received' => 600.00,
                'change_returned' => 80.40,
                'created_at'      => Carbon::parse('2025-10-23 10:23:00'),
                'updated_at'      => Carbon::parse('2025-10-23 10:23:00'),
                'items' => [
                    [
                        'product_id'   => 4,
                        'product_name' => 'Premium Brown Eggs (Dozen)',
                        'unit'         => 'Dozen',
                        'unit_price'   => 120.00,
                        'quantity'     => 1,
                        'stock_qty'    => 1,
                        'discount'     => 0.00,
                        'sgst_percent' => 2.00,
                        'cgst_percent' => 2.00,
                        'sgst_amount'  => 2.40,
                        'cgst_amount'  => 2.40,
                        'line_total'   => 124.80,
                    ],
                    [
                        'product_id'   => 2,
                        'product_name' => 'Refined Sunflower Oil',
                        'unit'         => 'Litre',
                        'unit_price'   => 175.00,
                        'quantity'     => 2,
                        'stock_qty'    => 2,
                        'discount'     => 0.00,
                        'sgst_percent' => 0.00,
                        'cgst_percent' => 0.00,
                        'sgst_amount'  => 0.00,
                        'cgst_amount'  => 0.00,
                        'line_total'   => 350.00,
                    ],
                    [
                        'product_id'   => 5,
                        'product_name' => 'Fresh Milk 1L Tetra',
                        'unit'         => 'Litre',
                        'unit_price'   => 65.00,
                        'quantity'     => 1,
                        'stock_qty'    => 1,
                        'discount'     => 0.00,
                        'sgst_percent' => 1.50,
                        'cgst_percent' => 1.50,
                        'sgst_amount'  => 0.98,
                        'cgst_amount'  => 0.98,
                        'line_total'   => 66.96,
                    ],
                ],
            ],
            [
                'bill_number'     => 'B-2025-002',
                'customer_name'   => 'Lekshimi',
                'payment_method'  => 'UPI',
                'subtotal'        => 395.00,
                'total_discount'  => 5.00,
                'total_sgst'      => 18.50,
                'total_cgst'      => 7.40,
                'grand_total'     => 415.90,
                'amount_received' => 415.90,
                'change_returned' => 0.00,
                'created_at'      => Carbon::parse('2025-10-23 11:45:00'),
                'updated_at'      => Carbon::parse('2025-10-23 11:45:00'),
                'items' => [
                    [
                        'product_id'   => 1,
                        'product_name' => 'Premium Basmati Rice',
                        'unit'         => 'kg',
                        'unit_price'   => 110.00,
                        'quantity'     => 2,
                        'stock_qty'    => 2,
                        'discount'     => 5.00,
                        'sgst_percent' => 5.00,
                        'cgst_percent' => 2.00,
                        'sgst_amount'  => 10.75,
                        'cgst_amount'  => 4.30,
                        'line_total'   => 230.05,
                    ],
                    [
                        'product_id'   => 2,
                        'product_name' => 'Refined Sunflower Oil',
                        'unit'         => 'Litre',
                        'unit_price'   => 175.00,
                        'quantity'     => 1,
                        'stock_qty'    => 1,
                        'discount'     => 0.00,
                        'sgst_percent' => 0.00,
                        'cgst_percent' => 0.00,
                        'sgst_amount'  => 0.00,
                        'cgst_amount'  => 0.00,
                        'line_total'   => 175.00,
                    ],
                ],
            ],
            [
                'bill_number'     => 'B-2025-003',
                'customer_name'   => 'Malar',
                'payment_method'  => 'Card',
                'subtotal'        => 265.00,
                'total_discount'  => 2.00,
                'total_sgst'      => 6.91,
                'total_cgst'      => 5.26,
                'grand_total'     => 275.17,
                'amount_received' => 300.00,
                'change_returned' => 24.83,
                'created_at'      => Carbon::parse('2025-10-26 10:23:00'),
                'updated_at'      => Carbon::parse('2025-10-26 10:23:00'),
                'items' => [
                    [
                        'product_id'   => 3,
                        'product_name' => 'Coffee Beans',
                        'unit'         => 'Packet',
                        'unit_price'   => 55.00,
                        'quantity'     => 3,
                        'stock_qty'    => 3,
                        'discount'     => 0.00,
                        'sgst_percent' => 3.00,
                        'cgst_percent' => 2.00,
                        'sgst_amount'  => 4.95,
                        'cgst_amount'  => 3.30,
                        'line_total'   => 173.25,
                    ],
                    [
                        'product_id'   => 6,
                        'product_name' => 'Whole Wheat Bread 400g',
                        'unit'         => 'Packet',
                        'unit_price'   => 50.00,
                        'quantity'     => 2,
                        'stock_qty'    => 2,
                        'discount'     => 2.00,
                        'sgst_percent' => 2.00,
                        'cgst_percent' => 2.00,
                        'sgst_amount'  => 1.96,
                        'cgst_amount'  => 1.96,
                        'line_total'   => 101.92,
                    ],
                ],
            ],
        ];

        foreach ($bills as $billData) {
            $items = $billData['items'];
            unset($billData['items']);

            $billId = DB::table('bills')->insertGetId($billData);

            foreach ($items as $item) {
                DB::table('bill_items')->insert(array_merge($item, [
                    'bill_id'    => $billId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $this->command->info('✅ Bills seeded: ' . count($bills));
    }
}
