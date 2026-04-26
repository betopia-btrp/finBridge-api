<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('processing_fee', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['description', 'min_amount', 'processing_fee']);
        });
    }
};
