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
        Schema::create('nozzle_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_sale_id')->constrained('daily_sales')->onDelete('cascade');
            $table->foreignId('nozzle_id')->constrained('nozzles')->onDelete('cascade');
            $table->decimal('closing_reading', 12, 2);
            $table->decimal('sale_qty', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['daily_sale_id', 'nozzle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nozzle_readings');
    }
};
