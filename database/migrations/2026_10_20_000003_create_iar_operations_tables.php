<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id(); $table->foreignId('impact_assessment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_response_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename'); $table->string('stored_path',1024);
            $table->string('disk',32)->default('local'); $table->string('mime_type',150); $table->unsignedBigInteger('size_bytes');
            $table->char('checksum',64); $table->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id(); $table->string('dataset_type',50); $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('filename'); $table->string('status',30)->default('pending');
            $table->unsignedInteger('total_rows')->default(0); $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0); $table->timestamps();
        });
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id(); $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number'); $table->string('column_name')->nullable(); $table->text('error_message'); $table->timestamps();
        });
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('impact_assessment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('iar_assignment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_type',80); $table->string('idempotency_key',191)->unique();
            $table->string('status',30)->default('pending'); $table->dateTime('sent_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type',120); $table->unsignedBigInteger('entity_id'); $table->string('action',100);
            $table->json('old_values')->nullable(); $table->json('new_values')->nullable(); $table->timestamps();
            $table->index(['entity_type','entity_id']);
        });
        Schema::create('assignment_exceptions', function (Blueprint $table) {
            $table->id(); $table->foreignId('impact_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('sap_code',100)->nullable(); $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exception_type',60); $table->string('resolution_status',30)->default('open');
            $table->text('details')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['assignment_exceptions','audit_logs','notification_logs','import_errors','import_batches','attachments'] as $name) Schema::dropIfExists($name);
    }
};
