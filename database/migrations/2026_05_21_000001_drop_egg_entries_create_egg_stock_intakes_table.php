<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_stock_intakes', function (Blueprint $table) {
            $table->id();
            $table->date('intake_date');
            $table->decimal('trays_received', 10, 2);
            $table->unsignedInteger('loose_eggs_received')->default(0);
            $table->unsignedInteger('eggs_per_tray')->default(30);
            $table->unsignedInteger('total_eggs');
            $table->decimal('cost_per_tray', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->decimal('cost_per_egg', 10, 4);
            $table->string('supplier_name', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('intake_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_stock_intakes');
    }
};
