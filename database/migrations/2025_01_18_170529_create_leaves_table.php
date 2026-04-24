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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('shift', length: 10)->nullable();
            $table->string('code', length: 30)->nullable();
            $table->string('fullname', length: 90)->nullable();
            $table->string('job', length: 50)->nullable();
            $table->string('type', length: 20)->nullable();
            $table->string('phone', length: 90)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('employee_id')->nullable();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('department_id')->nullable();
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
        Schema::dropIfExists('leaves');
    }
};
