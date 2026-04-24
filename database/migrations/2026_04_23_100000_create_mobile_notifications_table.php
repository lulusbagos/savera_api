<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_notifications')) {
            return;
        }

        Schema::create('mobile_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('username');
            $table->string('title');
            $table->text('message_html');
            $table->unsignedSmallInteger('status')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'user_id', 'status'], 'mobile_notifications_company_user_status_idx');
            $table->index(['company_id', 'username'], 'mobile_notifications_company_username_idx');
            $table->index('published_at', 'mobile_notifications_published_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notifications');
    }
};
