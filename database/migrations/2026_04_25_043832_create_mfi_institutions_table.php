<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfi_institutions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // owner = platform admin or creator
            $table->uuid('owner_id');


            // ENUM (same approach as users)
            $table->string('status')->default('active');

            $table->timestamps();
            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfi_institutions');
    }
};
