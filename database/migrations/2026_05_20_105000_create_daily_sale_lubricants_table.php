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
        if (!Schema::hasTable('daily_sale_lubricants')) {
            Schema::create('daily_sale_lubricants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('daily_sale_id')->constrained('daily_sales')->onDelete('cascade');
                $table->foreignId('lubricant_id')->constrained('lubricants')->onDelete('cascade');
                $table->integer('quantity');
                $table->decimal('price', 12, 2);
                $table->decimal('amount', 12, 2);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sale_lubricants');
    }
};
