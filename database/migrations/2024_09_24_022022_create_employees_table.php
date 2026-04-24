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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', length: 30)->nullable();
            $table->string('fullname', length: 90)->nullable();
            $table->string('email', length: 90)->nullable();
            $table->string('phone', length: 90)->nullable();
            $table->string('address', length: 90)->nullable();
            $table->string('city', length: 20)->nullable();
            $table->string('region', length: 20)->nullable();
            $table->string('pos', length: 10)->nullable();
            $table->string('country', length: 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('photo')->nullable();
            $table->string('job', length: 50)->nullable();
            $table->string('position', length: 90)->nullable();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->foreignId('mess_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('device_id')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
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
        Schema::dropIfExists('employees');
    }
};
