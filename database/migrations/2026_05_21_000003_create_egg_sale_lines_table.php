<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_entry_id')
                ->constrained('egg_daily_entries')
                ->onDelete('cascade');
            $table->decimal('price_per_egg', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();

            $table->index('daily_entry_id');
            $table->decimal('trays_sold', 10, 2)->default(0);
            $table->unsignedInteger('loose_eggs_sold')->default(0);
            $table->unsignedInteger('eggs_per_tray')->default(30);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_sale_lines');
    }
};
