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
        if (!Schema::hasColumn('nozzle_readings', 'testing_qty')) {
            Schema::table('nozzle_readings', function (Blueprint $table) {
                $table->decimal('testing_qty', 10, 2)->default(5.00)->after('closing_reading');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nozzle_readings', function (Blueprint $table) {
            $table->dropColumn('testing_qty');
        });
    }
};
