<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE mfi_institutions ALTER COLUMN status DROP DEFAULT");

        DB::statement("
            ALTER TABLE mfi_institutions 
            ALTER COLUMN status TYPE app_status_enum 
            USING status::text::app_status_enum
        ");

        DB::statement("ALTER TABLE mfi_institutions ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mfi_institutions ALTER COLUMN status DROP DEFAULT");
        DB::statement("ALTER TABLE mfi_institutions ALTER COLUMN status TYPE varchar");
        DB::statement("ALTER TABLE mfi_institutions ALTER COLUMN status SET DEFAULT 'active'");
    }
};
