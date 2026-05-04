<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_audit', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id', 80)->index();
            $table->unsignedInteger('chunk_index')->default(1);
            $table->unsignedInteger('chunk_count')->default(1);
            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->date('date')->index();
            $table->string('payload_hash', 128)->nullable();
            $table->unsignedBigInteger('payload_size')->nullable();
            $table->json('accepted_counts_json')->nullable();
            $table->json('parsed_counts_json')->nullable();
            $table->json('stored_counts_json')->nullable();
            $table->string('status', 32)->default('accepted')->index();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_audit');
    }
};

