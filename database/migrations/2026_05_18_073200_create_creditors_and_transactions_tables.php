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
        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('creditor_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creditor_id')->constrained('creditors')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('credit_amount', 12, 2)->default(0.00);
            $table->decimal('payment_amount', 12, 2)->default(0.00);
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditor_transactions');
        Schema::dropIfExists('creditors');
    }
};
