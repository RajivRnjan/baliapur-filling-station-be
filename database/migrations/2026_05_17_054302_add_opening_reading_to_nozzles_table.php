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
        Schema::table('nozzles', function (Blueprint $table) {
            $table->decimal('opening_reading', 12, 2)->default(0.00)->after('fuel_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nozzles', function (Blueprint $table) {
            $table->dropColumn('opening_reading');
        });
    }
};
