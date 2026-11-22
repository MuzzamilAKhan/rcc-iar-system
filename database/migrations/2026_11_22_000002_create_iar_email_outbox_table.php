<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('iar_email_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('impact_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_role', 20);
            $table->unsignedInteger('reminder_cycle');
            $table->string('idempotency_key', 191)->unique();
            $table->string('status', 24)->default('pending');
            $table->string('subject', 255);
            $table->text('body');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('available_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
            $table->index(['recipient_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iar_email_outbox');
    }
};
