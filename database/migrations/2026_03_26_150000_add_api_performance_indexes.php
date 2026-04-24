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
            DB::statement('CREATE INDEX IF NOT EXISTS companies_code_not_deleted_idx ON companies (code) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS devices_company_mac_not_deleted_idx ON devices (company_id, mac_address) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS employees_company_user_not_deleted_idx ON employees (company_id, user_id) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS employees_company_device_not_deleted_idx ON employees (company_id, device_id) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS summaries_user_date_sleep_not_deleted_idx ON summaries (user_id, send_date, sleep_type) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS summaries_employee_date_id_not_deleted_idx ON summaries (employee_id, send_date, id DESC) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS summaries_send_date_employee_not_deleted_idx ON summaries (send_date, employee_id) WHERE deleted_at IS NULL');

            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->index('code', 'companies_code_idx');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->index(['company_id', 'mac_address'], 'devices_company_mac_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index(['company_id', 'user_id'], 'employees_company_user_idx');
            $table->index(['company_id', 'device_id'], 'employees_company_device_idx');
        });

        Schema::table('summaries', function (Blueprint $table) {
            $table->index(['user_id', 'send_date', 'sleep_type'], 'summaries_user_date_sleep_idx');
            $table->index(['employee_id', 'send_date', 'id'], 'summaries_employee_date_id_idx');
            $table->index(['send_date', 'employee_id'], 'summaries_send_date_employee_idx');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS companies_code_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS devices_company_mac_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS employees_company_user_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS employees_company_device_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS summaries_user_date_sleep_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS summaries_employee_date_id_not_deleted_idx');
            DB::statement('DROP INDEX IF EXISTS summaries_send_date_employee_not_deleted_idx');

            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_code_idx');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_company_mac_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_company_user_idx');
            $table->dropIndex('employees_company_device_idx');
        });

        Schema::table('summaries', function (Blueprint $table) {
            $table->dropIndex('summaries_user_date_sleep_idx');
            $table->dropIndex('summaries_employee_date_id_idx');
            $table->dropIndex('summaries_send_date_employee_idx');
        });
    }
};
