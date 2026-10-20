<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imported_user_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('master_import_row_id')->constrained('master_import_rows')->restrictOnDelete();
            $table->string('source_email', 255);
            $table->string('verification_status', 30)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'master_import_row_id'], 'imported_user_source_unique');
            $table->index(['verification_status', 'user_id'], 'imported_user_verification_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_user_sources');
    }
};
