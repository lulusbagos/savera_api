<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            if (! Schema::hasColumn('summaries', 'fit_to_work_q1')) {
                $table->unsignedTinyInteger('fit_to_work_q1')->nullable()->after('is_fit3');
            }
            if (! Schema::hasColumn('summaries', 'fit_to_work_q2')) {
                $table->unsignedTinyInteger('fit_to_work_q2')->nullable()->after('fit_to_work_q1');
            }
            if (! Schema::hasColumn('summaries', 'fit_to_work_q3')) {
                $table->unsignedTinyInteger('fit_to_work_q3')->nullable()->after('fit_to_work_q2');
            }
            if (! Schema::hasColumn('summaries', 'fit_to_work_submitted_at')) {
                $table->timestamp('fit_to_work_submitted_at')->nullable()->after('fit_to_work_q3');
            }
        });
    }

    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            if (Schema::hasColumn('summaries', 'fit_to_work_submitted_at')) {
                $table->dropColumn('fit_to_work_submitted_at');
            }
            if (Schema::hasColumn('summaries', 'fit_to_work_q3')) {
                $table->dropColumn('fit_to_work_q3');
            }
            if (Schema::hasColumn('summaries', 'fit_to_work_q2')) {
                $table->dropColumn('fit_to_work_q2');
            }
            if (Schema::hasColumn('summaries', 'fit_to_work_q1')) {
                $table->dropColumn('fit_to_work_q1');
            }
        });
    }
};
