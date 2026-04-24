<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('active')->nullable();
            $table->string('active_text', length: 20)->nullable();
            $table->unsignedInteger('steps')->nullable();
            $table->string('steps_text', length: 20)->nullable();
            $table->unsignedInteger('heart_rate')->nullable();
            $table->string('heart_rate_text', length: 20)->nullable();
            $table->double('distance')->nullable();
            $table->string('distance_text', length: 20)->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->string('calories_text', length: 20)->nullable();
            $table->unsignedInteger('spo2')->nullable();
            $table->string('spo2_text', length: 20)->nullable();
            $table->unsignedInteger('stress')->nullable();
            $table->string('stress_text', length: 20)->nullable();
            $table->unsignedInteger('sleep')->nullable();
            $table->string('sleep_text', length: 20)->nullable();
            $table->unsignedInteger('sleep_start')->nullable();
            $table->unsignedInteger('sleep_end')->nullable();
            $table->string('sleep_type', length: 10)->nullable();
            $table->unsignedInteger('deep_sleep')->nullable();
            $table->unsignedInteger('light_sleep')->nullable();
            $table->unsignedInteger('rem_sleep')->nullable();
            $table->unsignedInteger('awake')->nullable();
            $table->unsignedInteger('wakeup')->nullable();
            $table->date('send_date')->nullable();
            $table->time('send_time')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('employee_id')->nullable();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->foreignId('shift_id')->nullable();
            $table->foreignId('device_id')->nullable();
            $table->timestamp('device_time')->nullable();
            $table->string('app_version', length: 50)->nullable();
            $table->unsignedTinyInteger('is_fit1')->nullable();
            $table->unsignedTinyInteger('is_fit2')->nullable();
            $table->unsignedTinyInteger('is_fit3')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
