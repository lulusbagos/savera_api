<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_notifications')) {
            return;
        }

        Schema::table('mobile_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_notifications', 'source_type')) {
                $table->string('source_type', 50)->nullable()->after('message_html');
            }
            if (! Schema::hasColumn('mobile_notifications', 'source_ref')) {
                $table->string('source_ref', 120)->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('mobile_notifications', 'source_event_at')) {
                $table->timestamp('source_event_at')->nullable()->after('source_ref');
            }
            if (! Schema::hasColumn('mobile_notifications', 'payload_json')) {
                $table->json('payload_json')->nullable()->after('source_event_at');
            }
        });

        Schema::table('mobile_notifications', function (Blueprint $table) {
            $table->index(['company_id', 'source_type', 'source_event_at'], 'mobile_notifications_company_source_event_idx');
            $table->unique(['company_id', 'user_id', 'source_ref'], 'mobile_notifications_company_user_source_ref_uk');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mobile_notifications')) {
            return;
        }

        Schema::table('mobile_notifications', function (Blueprint $table) {
            $table->dropUnique('mobile_notifications_company_user_source_ref_uk');
            $table->dropIndex('mobile_notifications_company_source_event_idx');
        });

        Schema::table('mobile_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('mobile_notifications', 'payload_json')) {
                $table->dropColumn('payload_json');
            }
            if (Schema::hasColumn('mobile_notifications', 'source_event_at')) {
                $table->dropColumn('source_event_at');
            }
            if (Schema::hasColumn('mobile_notifications', 'source_ref')) {
                $table->dropColumn('source_ref');
            }
            if (Schema::hasColumn('mobile_notifications', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
