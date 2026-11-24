<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_action_evidence_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('action_id')->unique();
            $table->foreign('action_id', 'ccr_act_ev_action_fk')->references('id')->on('ccr_actions')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->text('owner_explanation')->nullable();
            $table->string('evidence_reference', 150)->nullable();
            $table->string('evidence_revision', 80)->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->foreign('submitted_by_user_id', 'ccr_act_ev_submitter_fk')->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id', 'ccr_act_ev_reviewer_fk')->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('ccr_action_evidence_reviews'); }
};
