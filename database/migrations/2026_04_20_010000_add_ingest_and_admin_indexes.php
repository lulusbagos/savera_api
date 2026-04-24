<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_company_employee_date_not_deleted_idx ON tickets (company_id, employee_id, date) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_company_date_not_deleted_idx ON tickets (company_id, date) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS leaves_company_employee_date_not_deleted_idx ON leaves (company_id, employee_id, date) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS leaves_company_date_not_deleted_idx ON leaves (company_id, date) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS banners_company_seq_id_not_deleted_idx ON banners (company_id, seq, id) WHERE deleted_at IS NULL');

            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['company_id', 'employee_id', 'date'], 'tickets_company_employee_date_idx');
            $table->index(['company_id', 'date'], 'tickets_company_date_idx');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->index(['company_id', 'employee_id', 'date'], 'leaves_company_employee_date_idx');
            $table->index(['company_id', 'date'], 'leaves_company_date_idx');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->index(['company_id', 'seq', 'id'], 'banners_company_seq_id_idx');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS tickets_company_employee_date_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS tickets_company_date_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS leaves_company_employee_date_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS leaves_company_date_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS banners_company_seq_id_not_deleted_idx');

            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_company_employee_date_idx');
            $table->dropIndex('tickets_company_date_idx');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropIndex('leaves_company_employee_date_idx');
            $table->dropIndex('leaves_company_date_idx');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('banners_company_seq_id_idx');
        });
    }
};
