<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('email_verified_at')->index();
            }
            if (! Schema::hasColumn('users', 'employee_code')) {
                $table->string('employee_code', 50)->nullable()->after('email')->index();
            }
            if (! Schema::hasColumn('users', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('password')->index();
            }
            if (! Schema::hasColumn('users', 'is_api_managed')) {
                $table->boolean('is_api_managed')->default(false)->after('source_type')->index();
            }
            if (! Schema::hasColumn('users', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('is_api_managed');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'external_id')) {
                $table->string('external_id', 50)->nullable()->after('code')->index();
            }
            if (! Schema::hasColumn('companies', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('status')->index();
            }
            if (! Schema::hasColumn('companies', 'is_api_managed')) {
                $table->boolean('is_api_managed')->default(false)->after('source_type')->index();
            }
            if (! Schema::hasColumn('companies', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('is_api_managed');
            }
            if (! Schema::hasColumn('companies', 'sync_payload')) {
                $table->json('sync_payload')->nullable()->after('last_synced_at');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'external_id')) {
                $table->string('external_id', 50)->nullable()->after('company_id')->index();
            }
            if (! Schema::hasColumn('departments', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('status')->index();
            }
            if (! Schema::hasColumn('departments', 'is_api_managed')) {
                $table->boolean('is_api_managed')->default(false)->after('source_type')->index();
            }
            if (! Schema::hasColumn('departments', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('is_api_managed');
            }
            if (! Schema::hasColumn('departments', 'sync_payload')) {
                $table->json('sync_payload')->nullable()->after('last_synced_at');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('status')->index();
            }
            if (! Schema::hasColumn('employees', 'is_api_managed')) {
                $table->boolean('is_api_managed')->default(false)->after('source_type')->index();
            }
            if (! Schema::hasColumn('employees', 'external_employee_id')) {
                $table->string('external_employee_id', 50)->nullable()->after('code')->index();
            }
            if (! Schema::hasColumn('employees', 'external_department_name')) {
                $table->string('external_department_name', 150)->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('employees', 'external_position_name')) {
                $table->string('external_position_name', 150)->nullable()->after('position');
            }
            if (! Schema::hasColumn('employees', 'external_status')) {
                $table->boolean('external_status')->nullable()->after('status');
            }
            if (! Schema::hasColumn('employees', 'allow_manual_override')) {
                $table->boolean('allow_manual_override')->default(true)->after('external_status');
            }
            if (! Schema::hasColumn('employees', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('allow_manual_override');
            }
            if (! Schema::hasColumn('employees', 'sync_payload')) {
                $table->json('sync_payload')->nullable()->after('synced_at');
            }
        });

        Schema::table('devices', function (Blueprint $table) {
            if (! Schema::hasColumn('devices', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('is_active')->index();
            }
            if (! Schema::hasColumn('devices', 'is_api_managed')) {
                $table->boolean('is_api_managed')->default(false)->after('source_type')->index();
            }
            if (! Schema::hasColumn('devices', 'external_device_id')) {
                $table->string('external_device_id', 50)->nullable()->after('serial_number')->index();
            }
            if (! Schema::hasColumn('devices', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('is_api_managed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            foreach (['last_synced_at', 'external_device_id', 'is_api_managed', 'source_type'] as $column) {
                if (Schema::hasColumn('devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            foreach (['sync_payload', 'synced_at', 'allow_manual_override', 'external_status', 'external_position_name', 'external_department_name', 'external_employee_id', 'is_api_managed', 'source_type'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            foreach (['sync_payload', 'last_synced_at', 'is_api_managed', 'source_type', 'external_id'] as $column) {
                if (Schema::hasColumn('departments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            foreach (['sync_payload', 'last_synced_at', 'is_api_managed', 'source_type', 'external_id'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['last_synced_at', 'is_api_managed', 'source_type', 'employee_code', 'company_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
