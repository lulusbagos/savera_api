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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('brand', length: 50)->nullable();
            $table->string('device_name', length: 50)->nullable();
            $table->string('mac_address', length: 50)->nullable();
            $table->string('auth_key', length: 50)->nullable();
            $table->string('serial_number', length: 50)->nullable();
            $table->string('license_number', length: 50)->nullable();
            $table->string('app_version', length: 50)->nullable();
            $table->string('os_name', length: 20)->nullable();
            $table->string('os_version', length: 30)->nullable();
            $table->string('os_sdk', length: 10)->nullable();
            $table->string('phone_brand', length: 50)->nullable();
            $table->string('phone_model', length: 50)->nullable();
            $table->string('phone_product', length: 50)->nullable();
            $table->boolean('is_active')->nullable();
            $table->foreignId('company_id')->nullable();
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
        Schema::dropIfExists('devices');
    }
};
