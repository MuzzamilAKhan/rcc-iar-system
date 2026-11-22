<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('change_control_records', function (Blueprint $table) {
            $table->id();
            $table->string('ccr_number', 50)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('change_type', 40);
            $table->text('current_state');
            $table->text('proposed_state');
            $table->text('reason_for_change');
            $table->text('affected_scope')->nullable();
            $table->string('change_duration', 20);
            $table->date('target_implementation_date')->nullable();
            $table->string('status', 40)->default('draft');
            $table->timestamps();
            $table->index(['created_by', 'status']);
        });
        Schema::create('ccr_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_control_record_id')->constrained('change_control_records')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['change_control_record_id','product_id'], 'ccr_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccr_products');
        Schema::dropIfExists('change_control_records');
    }
};
