<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_sme_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_control_record_id')->constrained('change_control_records')->cascadeOnDelete();
            $table->string('area_code', 40);
            $table->foreignId('assigned_to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('assigned');
            $table->dateTime('assigned_at');
            $table->timestamps();
            $table->unique(['change_control_record_id', 'area_code'], 'ccr_sme_area_once');
            $table->index(['assigned_to_user_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('ccr_sme_assignments'); }
};
