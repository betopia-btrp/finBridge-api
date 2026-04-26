<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name'); // starter, growth, enterprise

            $table->integer('max_staff'); // number of employees

            $table->integer('max_borrowers'); // total borrowers

            $table->integer('max_active_loans'); // active loans only

            $table->integer('price_bdt')->nullable(); // monthly price in BDT

            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
