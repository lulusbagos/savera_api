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
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->string('period', length: 10);
            $table->string('code', length: 30)->nullable();
            $table->string('fullname', length: 90)->nullable();
            $table->string('department', length: 90)->nullable();
            $table->string('d01', length: 10)->nullable();
            $table->string('d02', length: 10)->nullable();
            $table->string('d03', length: 10)->nullable();
            $table->string('d04', length: 10)->nullable();
            $table->string('d05', length: 10)->nullable();
            $table->string('d06', length: 10)->nullable();
            $table->string('d07', length: 10)->nullable();
            $table->string('d08', length: 10)->nullable();
            $table->string('d09', length: 10)->nullable();
            $table->string('d10', length: 10)->nullable();
            $table->string('d11', length: 10)->nullable();
            $table->string('d12', length: 10)->nullable();
            $table->string('d13', length: 10)->nullable();
            $table->string('d14', length: 10)->nullable();
            $table->string('d15', length: 10)->nullable();
            $table->string('d16', length: 10)->nullable();
            $table->string('d17', length: 10)->nullable();
            $table->string('d18', length: 10)->nullable();
            $table->string('d19', length: 10)->nullable();
            $table->string('d20', length: 10)->nullable();
            $table->string('d21', length: 10)->nullable();
            $table->string('d22', length: 10)->nullable();
            $table->string('d23', length: 10)->nullable();
            $table->string('d24', length: 10)->nullable();
            $table->string('d25', length: 10)->nullable();
            $table->string('d26', length: 10)->nullable();
            $table->string('d27', length: 10)->nullable();
            $table->string('d28', length: 10)->nullable();
            $table->string('d29', length: 10)->nullable();
            $table->string('d30', length: 10)->nullable();
            $table->string('d31', length: 10)->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->foreignId('user_id')->nullable();
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
        Schema::dropIfExists('rosters');
    }
};
