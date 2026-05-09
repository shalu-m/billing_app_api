<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->unique();
            $table->unsignedInteger('opening_stock')->default(0);
            $table->unsignedInteger('fresh_arrivals')->default(0);
            $table->unsignedInteger('eggs_sold')->default(0);
            $table->unsignedInteger('damaged_eggs')->default(0);
            // closing_stock, revenue, profit are computed in PHP (model accessors)
            // to avoid DB-level expression issues across MySQL versions
            $table->decimal('cost_per_egg', 8, 2);
            $table->decimal('selling_price', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_entries');
    }
};
