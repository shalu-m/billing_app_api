<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('unit', 50)->default('piece');
            $table->decimal('cost_price', 10, 4);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('sgst', 5, 2)->default(0.00);
            $table->decimal('cgst', 5, 2)->default(0.00);
            $table->decimal('stock', 10, 3)->default(0);
            $table->string('barcode', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            
            // Wholesale / Bulk fields (nullable for simple products)
            $table->string('purchase_unit', 50)->nullable();
            $table->decimal('purchase_qty', 10, 3)->nullable();
            $table->decimal('wholesale_cost', 10, 2)->nullable();
            $table->decimal('wholesale_price', 10, 2)->nullable();
            
            $table->softDeletes();
            $table->timestamps();

            $table->index('name');
            $table->index('barcode');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
