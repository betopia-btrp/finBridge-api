<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('mfi_id')->nullable();
            $table->uuid('subscription_id')->nullable();

            $table->integer('amount');
            $table->string('currency')->default('BDT');

            $table->string('status');
            // pending, success, failed

            $table->string('payment_gateway');
            // sslcommerz

            $table->string('gateway_transaction_id')->nullable();

            $table->timestamps();

            $table->foreign('mfi_id')->references('id')->on('mfi_institutions')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
