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
        if (!Schema::hasColumn('daily_sales', 'online_creditor_name')) {
            Schema::table('daily_sales', function (Blueprint $table) {
                $table->string('online_creditor_name')->nullable()->after('online');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_sales', function (Blueprint $table) {
            $table->dropColumn('online_creditor_name');
        });
    }
};
