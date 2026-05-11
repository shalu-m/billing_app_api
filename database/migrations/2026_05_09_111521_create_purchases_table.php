<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('received_unit', 50); // Unit delivered in — bag or kg
            $table->decimal('received_qty', 10, 3); //  How many bags/kg arrived  e.g. 10
            $table->decimal('converted_qty', 10, 3); // Always in base unit (kg)
            $table->decimal('cost_per_unit', 10, 2); //  Cost paid per received_unit (per bag)
            $table->decimal('total_cost', 10, 2); // received_qty × cost_per_unit
            $table->date('purchase_date');
            $table->string('supplier_name', 150)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('purchase_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
