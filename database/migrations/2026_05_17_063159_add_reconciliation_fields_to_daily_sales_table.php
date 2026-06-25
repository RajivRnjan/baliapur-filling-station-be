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
        Schema::table('daily_sales', function (Blueprint $table) {
            $table->decimal('credit_payment_received', 12, 2)->default(0.00)->after('density');
            $table->decimal('ac_transfer', 12, 2)->default(0.00)->after('credit_payment_received');
            $table->decimal('online', 12, 2)->default(0.00)->after('ac_transfer');
            $table->decimal('creditor', 12, 2)->default(0.00)->after('online');
            $table->decimal('expense', 12, 2)->default(0.00)->after('creditor');
            $table->decimal('staff', 12, 2)->default(0.00)->after('expense');
            $table->decimal('owner_1', 12, 2)->default(0.00)->after('staff');
            $table->decimal('owner_2', 12, 2)->default(0.00)->after('owner_1');
            $table->decimal('cash_collection', 12, 2)->default(0.00)->after('owner_2');
            $table->decimal('coin', 12, 2)->default(0.00)->after('cash_collection');
            $table->decimal('miss', 12, 2)->default(0.00)->after('coin');
            $table->decimal('balance_in_pump', 12, 2)->default(0.00)->after('miss');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_sales', function (Blueprint $table) {
            $table->dropColumn([
                'credit_payment_received',
                'ac_transfer',
                'online',
                'creditor',
                'expense',
                'staff',
                'owner_1',
                'owner_2',
                'cash_collection',
                'coin',
                'miss',
                'balance_in_pump'
            ]);
        });
    }
};
