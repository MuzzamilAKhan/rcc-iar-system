<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_control_record_id');
            $table->foreign('change_control_record_id', 'ccr_action_ccr_fk')->references('id')->on('change_control_records')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->string('impact_area_code', 50)->nullable();
            $table->unsignedBigInteger('owner_user_id');
            $table->foreign('owner_user_id', 'ccr_action_owner_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unsignedBigInteger('created_by_user_id');
            $table->foreign('created_by_user_id', 'ccr_action_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $table->date('due_date');
            $table->unsignedBigInteger('depends_on_action_id')->nullable();
            $table->foreign('depends_on_action_id', 'ccr_action_dep_fk')->references('id')->on('ccr_actions')->restrictOnDelete();
            $table->string('status', 32)->default('proposed');
            $table->timestamps();
            $table->index(['change_control_record_id', 'status'], 'ccr_action_ccr_status_idx');
            $table->index(['owner_user_id', 'due_date'], 'ccr_action_owner_due_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('ccr_actions'); }
};
