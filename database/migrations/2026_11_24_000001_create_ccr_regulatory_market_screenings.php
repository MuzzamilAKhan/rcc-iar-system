<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_regulatory_market_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_control_record_id');
            $table->foreign('change_control_record_id', 'ccr_mkt_ccr_fk')->references('id')->on('change_control_records')->restrictOnDelete();
            $table->foreignId('country_id');
            $table->foreign('country_id', 'ccr_mkt_country_fk')->references('id')->on('countries')->restrictOnDelete();
            $table->string('jurisdiction_hint', 40); // routing hint ONLY, not a regulatory determination
            $table->string('status', 40)->default('pending_regulatory_screening');
            $table->foreignId('created_by_user_id');
            $table->foreign('created_by_user_id', 'ccr_mkt_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['change_control_record_id', 'country_id'], 'ccr_market_country_unique');
            $table->index(['change_control_record_id', 'status'], 'ccr_market_status_idx');
        });
        Schema::create('ccr_regulatory_market_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccr_regulatory_market_screening_id');
            $table->foreign('ccr_regulatory_market_screening_id', 'ccr_msrc_screen_fk')->references('id')->on('ccr_regulatory_market_screenings')->restrictOnDelete();
            $table->foreignId('product_registration_id');
            $table->foreign('product_registration_id', 'ccr_msrc_reg_fk')->references('id')->on('product_registrations')->restrictOnDelete();
            $table->foreignId('product_id');
            $table->foreign('product_id', 'ccr_msrc_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->string('source_registration_status', 50);
            $table->timestamps();
            $table->unique(['ccr_regulatory_market_screening_id', 'product_registration_id'], 'ccr_market_source_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ccr_regulatory_market_sources');
        Schema::dropIfExists('ccr_regulatory_market_screenings');
    }
};
