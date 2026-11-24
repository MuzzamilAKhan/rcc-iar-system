<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_document_impacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_control_record_id');
            $table->foreign('change_control_record_id', 'ccr_doc_ccr_fk')->references('id')->on('change_control_records')->restrictOnDelete();
            $table->string('document_identifier', 120);
            $table->string('document_revision', 80);
            $table->string('document_title', 250);
            $table->string('impact_area_code', 50)->nullable();
            $table->unsignedBigInteger('related_action_id')->nullable();
            $table->foreign('related_action_id', 'ccr_doc_action_fk')->references('id')->on('ccr_actions')->restrictOnDelete();
            $table->string('status', 24)->default('potential');
            $table->text('impact_rationale');
            $table->text('confirmation_rationale')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->foreign('created_by_user_id', 'ccr_doc_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->foreign('confirmed_by_user_id', 'ccr_doc_confirmer_fk')->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['change_control_record_id','document_identifier','document_revision'], 'ccr_doc_ccr_identifier_rev_unique');
        });
        Schema::create('ccr_evidence_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_control_record_id');
            $table->foreign('change_control_record_id', 'ccr_evidence_ccr_fk')->references('id')->on('change_control_records')->restrictOnDelete();
            $table->unsignedBigInteger('document_impact_id')->nullable();
            $table->foreign('document_impact_id', 'ccr_evidence_doc_fk')->references('id')->on('ccr_document_impacts')->restrictOnDelete();
            $table->unsignedBigInteger('related_action_id')->nullable();
            $table->foreign('related_action_id', 'ccr_evidence_action_fk')->references('id')->on('ccr_actions')->restrictOnDelete();
            $table->string('reference_identifier', 150);
            $table->string('reference_revision', 80)->nullable();
            $table->text('evidence_description');
            $table->string('evidence_status', 24)->default('referenced');
            $table->unsignedBigInteger('created_by_user_id');
            $table->foreign('created_by_user_id', 'ccr_evidence_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['change_control_record_id','document_impact_id'], 'ccr_evidence_ccr_doc_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ccr_evidence_references');
        Schema::dropIfExists('ccr_document_impacts');
    }
};
