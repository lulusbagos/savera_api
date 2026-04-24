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
        Schema::create('p5m', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('shift', length: 10)->nullable();
            $table->string('code', length: 30)->nullable();
            $table->string('fullname', length: 90)->nullable();
            $table->string('job', length: 50)->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('status', length: 10)->nullable();
            $table->string('platform', length: 20)->nullable();
            $table->foreignId('quiz_id')->nullable();
            $table->foreignId('employee_id')->nullable();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('p5m_point', function (Blueprint $table) {
            $table->id();
            $table->char('key', length: 1);
            $table->char('answer', length: 1);
            $table->unsignedSmallInteger('seq')->nullable();
            $table->unsignedSmallInteger('point')->nullable();
            $table->foreignId('p5m_id')->nullable();
            $table->foreignId('quiz_id')->nullable();
            $table->foreignId('item_id')->nullable();
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
        Schema::dropIfExists('p5m');
        Schema::dropIfExists('p5m_point');
    }
};
