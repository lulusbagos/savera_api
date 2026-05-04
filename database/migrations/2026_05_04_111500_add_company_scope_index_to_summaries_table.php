<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "CREATE INDEX IF NOT EXISTS summaries_company_user_date_sleep_not_deleted_idx
            ON summaries (company_id, user_id, send_date, sleep_type)
            WHERE deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS summaries_company_user_date_sleep_not_deleted_idx');
    }
};

