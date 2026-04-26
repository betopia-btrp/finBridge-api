<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('mfi_id'); // belongs to MFI

            $table->string('name'); // product name

            $table->decimal('max_amount', 12, 2); // BDT
            $table->decimal('interest_rate', 5, 2); // percentage

            $table->integer('duration_months');

            $table->string('status')->default('active');

            $table->timestamps();

            $table->foreign('mfi_id')
                ->references('id')
                ->on('mfi_institutions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
