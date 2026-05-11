<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── bills ────────────────────────────────────────────
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 30)->unique();
            $table->string('customer_name', 150)->default('Walk-in');
            $table->enum('payment_method', ['Cash', 'UPI', 'Card'])->default('Cash');
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('total_discount', 10, 2)->default(0.00);
            $table->decimal('total_sgst', 10, 2)->default(0.00);
            $table->decimal('total_cgst', 10, 2)->default(0.00);
            $table->decimal('grand_total', 10, 2)->default(0.00);
            $table->decimal('total_profit', 10, 2)->default(0.00);
            $table->decimal('amount_received', 10, 2)->default(0.00);
            $table->decimal('change_returned', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('bill_number');
            $table->index('customer_name');
            $table->index('payment_method');
            $table->index('created_at');
        });

        // ── bill_items ───────────────────────────────────────
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')
                  ->constrained('bills')
                  ->onDelete('cascade');
            $table->foreignId('product_id')
                  ->nullable()
                  ->constrained('products')
                  ->onDelete('set null');
            $table->string('product_name', 200);
            $table->string('unit', 50)->default('piece');
            $table->enum('sell_mode', ['loose', 'wholesale'])->default('loose');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('stock_qty', 10, 3)->default(0);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('sgst_percent', 5, 2)->default(0.00);
            $table->decimal('cgst_percent', 5, 2)->default(0.00);
            $table->decimal('sgst_amount', 10, 2)->default(0.00);
            $table->decimal('cgst_amount', 10, 2)->default(0.00);
            $table->decimal('line_total', 10, 2);
            $table->decimal('line_profit', 10, 2)->default(0.00);
            $table->timestamps();

            $table->index('bill_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};
