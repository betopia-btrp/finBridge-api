<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DROP TYPE IF EXISTS user_role_enum CASCADE");
        DB::statement("CREATE TYPE user_role_enum AS ENUM ('platform_admin', 'mfi_admin', 'entrepreneur')");

        DB::statement("DROP TYPE IF EXISTS app_status_enum CASCADE");
        DB::statement("CREATE TYPE app_status_enum AS ENUM ('active', 'inactive', 'suspended')");

        DB::statement("DROP TYPE IF EXISTS interest_type_enum CASCADE");
        DB::statement("CREATE TYPE interest_type_enum AS ENUM ('flat', 'reducing')");

        DB::statement("DROP TYPE IF EXISTS repayment_freq_enum CASCADE");
        DB::statement("CREATE TYPE repayment_freq_enum AS ENUM ('daily', 'weekly', 'monthly')");

        DB::statement("DROP TYPE IF EXISTS repayment_status_enum CASCADE");
        DB::statement("CREATE TYPE repayment_status_enum AS ENUM ('pending', 'paid', 'overdue')");

        DB::statement("DROP TYPE IF EXISTS mfi_admin_role_enum CASCADE");
        DB::statement("CREATE TYPE mfi_admin_role_enum AS ENUM ('owner', 'manager', 'staff')");
    }

    public function down(): void
    {
        DB::statement("DROP TYPE IF EXISTS user_role_enum CASCADE");
        DB::statement("DROP TYPE IF EXISTS app_status_enum CASCADE");
        DB::statement("DROP TYPE IF EXISTS interest_type_enum CASCADE");
        DB::statement("DROP TYPE IF EXISTS repayment_freq_enum CASCADE");
        DB::statement("DROP TYPE IF EXISTS repayment_status_enum CASCADE");
        DB::statement("DROP TYPE IF EXISTS mfi_admin_role_enum CASCADE");
    }
};
