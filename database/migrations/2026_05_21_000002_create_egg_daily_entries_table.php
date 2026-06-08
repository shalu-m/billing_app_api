<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_daily_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->unique();
            $table->unsignedInteger('opening_stock')->default(0);
            $table->unsignedInteger('new_stock_today')->default(0);
            $table->unsignedInteger('total_eggs_sold')->default(0);
            $table->unsignedInteger('damaged_eggs')->default(0);
            $table->unsignedInteger('closing_stock')->default(0);
            $table->json('stock_layers')->nullable();
            $table->decimal('avg_cost_per_egg', 10, 4)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->decimal('gross_profit', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_daily_entries');
    }
};
