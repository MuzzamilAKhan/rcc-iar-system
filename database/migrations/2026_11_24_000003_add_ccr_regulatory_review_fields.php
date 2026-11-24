<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ccr_regulatory_drafts', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id', 'ccr_rd_reviewer_fk')->references('id')->on('users')->restrictOnDelete();
            $table->text('review_comment')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('ccr_regulatory_drafts', function (Blueprint $table) {
            $table->dropForeign('ccr_rd_reviewer_fk');
            $table->dropColumn(['submitted_at','reviewed_at','reviewed_by_user_id','review_comment']);
        });
    }
};
