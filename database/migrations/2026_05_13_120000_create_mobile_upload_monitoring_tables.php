<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_upload_batches', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id', 120);
            $table->string('source', 40)->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->date('upload_date')->nullable()->index();
            $table->string('status', 32)->default('received')->index();
            $table->unsignedInteger('chunks_total')->default(1);
            $table->unsignedInteger('chunks_received')->default(0);
            $table->unsignedBigInteger('payload_bytes_total')->default(0);
            $table->string('payload_hash', 128)->nullable();
            $table->string('idempotency_key', 160)->nullable()->index();
            $table->unsignedBigInteger('summary_id')->nullable()->index();
            $table->json('accepted_counts_json')->nullable();
            $table->json('parsed_counts_json')->nullable();
            $table->json('stored_counts_json')->nullable();
            $table->json('extra_json')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_chunk_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['source', 'upload_id']);
            $table->index(['source', 'status', 'received_at']);
            $table->index(['company_id', 'upload_date', 'status']);
        });

        Schema::create('mobile_upload_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_upload_batch_id')
                ->constrained('mobile_upload_batches')
                ->cascadeOnDelete();
            $table->string('upload_id', 120);
            $table->string('source', 40)->index();
            $table->unsignedInteger('chunk_index')->default(1);
            $table->unsignedInteger('chunk_count')->default(1);
            $table->string('status', 32)->default('received')->index();
            $table->string('payload_hash', 128)->nullable();
            $table->unsignedBigInteger('payload_size')->default(0);
            $table->string('storage_path', 500)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['source', 'upload_id', 'chunk_index']);
            $table->index(['mobile_upload_batch_id', 'status']);
            $table->index(['source', 'status', 'received_at']);
        });

        Schema::create('worker_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('worker_name', 120)->unique();
            $table->string('queue_connection', 80)->nullable();
            $table->string('queue_name', 120)->nullable();
            $table->string('status', 32)->default('idle')->index();
            $table->string('current_upload_id', 120)->nullable();
            $table->string('current_source', 40)->nullable();
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_heartbeats');
        Schema::dropIfExists('mobile_upload_chunks');
        Schema::dropIfExists('mobile_upload_batches');
    }
};
