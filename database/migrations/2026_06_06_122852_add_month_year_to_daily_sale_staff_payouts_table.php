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
        Schema::table('daily_sale_staff_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_sale_staff_payouts', 'salary_month')) {
                $table->unsignedTinyInteger('salary_month')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('daily_sale_staff_payouts', 'salary_year')) {
                $table->unsignedSmallInteger('salary_year')->nullable()->after('salary_month');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_sale_staff_payouts', function (Blueprint $table) {
            $table->dropColumn(['salary_month', 'salary_year']);
        });
    }
};
