<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_sme_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccr_sme_assignment_id')->unique()->constrained('ccr_sme_assignments')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('answer', 20); // yes / no / uncertain
            $table->string('impact_conclusion', 40); // potential_impact / no_impact / further_assessment
            $table->text('rationale');
            $table->text('evidence_references')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('ccr_sme_responses'); }
};
