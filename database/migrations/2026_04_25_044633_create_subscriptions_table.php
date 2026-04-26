<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // 🔗 relationships
            $table->uuid('mfi_id');
            $table->uuid('plan_id');

            // 📅 subscription lifecycle
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // 🔁 active flag
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ✅ foreign keys
            $table->foreign('mfi_id')
                ->references('id')
                ->on('mfi_institutions')
                ->cascadeOnDelete();

            $table->foreign('plan_id')
                ->references('id')
                ->on('subscription_plans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
