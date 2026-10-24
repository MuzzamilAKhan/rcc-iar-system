<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registry_import_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('filename', 255);
            $table->char('file_sha256', 64);
            $table->string('status', 24)->default('pending')->index();
            $table->text('submission_reason');
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('total_rows');
            $table->timestamps();
        });
        Schema::create('registry_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registry_import_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('excel_row');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('existing_registration_id')->nullable()->constrained('product_registrations')->restrictOnDelete();
            $table->string('sap_code', 100);
            $table->string('country_name', 150);
            $table->string('change_type', 32);
            $table->string('old_status', 50)->nullable();
            $table->string('old_number', 150)->nullable();
            $table->string('new_status', 50);
            $table->string('new_number', 150)->nullable();
            $table->timestamps();
            $table->unique(['registry_import_request_id', 'product_id', 'country_id'], 'registry_import_item_pair_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('registry_import_items');
        Schema::dropIfExists('registry_import_requests');
    }
};
