<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id'); // entrepreneur
            $table->uuid('mfi_id');
            $table->uuid('loan_product_id');

            $table->decimal('amount', 12, 2);
            $table->integer('duration_months');

            $table->text('purpose')->nullable();

            $table->string('status')->default('pending');
            // pending, approved, rejected

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('mfi_id')
                ->references('id')
                ->on('mfi_institutions')
                ->cascadeOnDelete();

            $table->foreign('loan_product_id')
                ->references('id')
                ->on('loan_products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
