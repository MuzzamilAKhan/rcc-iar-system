<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_impact_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_control_record_id')->constrained('change_control_records')->cascadeOnDelete();
            $table->string('area_code', 40);
            $table->string('decision', 20); // selected | not_selected
            $table->boolean('suggested_by_rule')->default(false);
            $table->text('rationale')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['change_control_record_id', 'area_code'], 'ccr_impact_area_once');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ccr_impact_decisions');
    }
};
