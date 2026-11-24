<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_regulatory_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_id')->unique();
            $table->foreign('screening_id', 'ccr_rdraft_screen_fk')->references('id')->on('ccr_regulatory_market_screenings')->restrictOnDelete();
            $table->unsignedBigInteger('assigned_to_user_id');
            $table->foreign('assigned_to_user_id', 'ccr_rdraft_assignee_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unsignedBigInteger('assigned_by_user_id');
            $table->foreign('assigned_by_user_id', 'ccr_rdraft_assigner_fk')->references('id')->on('users')->restrictOnDelete();
            $table->string('status', 20)->default('assigned'); // assigned | draft; no approval
            $table->string('jurisdiction_route', 40)->nullable();
            $table->string('preliminary_outcome', 40)->nullable();
            $table->text('rationale')->nullable();
            $table->text('evidence_references')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ccr_regulatory_drafts'); }
};
