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
        Schema::create('lubricants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('qty_name'); // e.g., '1 lt', '500Ml', '900ml', '20Lt'
            $table->decimal('price', 12, 2)->default(0.00);
            $table->integer('stock')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lubricants');
    }
};
