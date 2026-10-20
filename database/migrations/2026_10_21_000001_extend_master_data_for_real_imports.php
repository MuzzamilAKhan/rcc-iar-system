<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('source_region_label')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('uno_medical_code', 100)->nullable();
            $table->string('other_erp_code', 100)->nullable();
            $table->string('product_code_on_label', 150)->nullable();
            $table->string('global_commercial_status', 120)->nullable();
            $table->json('source_metadata')->nullable();
        });
        Schema::table('product_registrations', function (Blueprint $table) {
            $table->string('source_registration_status', 200)->nullable();
            $table->string('source_commercial_status', 200)->nullable();
            $table->string('product_registration_type', 150)->nullable();
            $table->string('product_description_in_market')->nullable();
            $table->json('source_metadata')->nullable();
            // No unique product-country constraint: source data may contain multiple regulatory records.
        });
        Schema::create('user_business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_unit_id')->constrained()->restrictOnDelete();
            $table->text('product_process_expertise')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'business_unit_id']);
        });
        Schema::create('master_import_issues', function (Blueprint $table) {
            $table->id();
            $table->string('source_file', 120);
            $table->unsignedInteger('source_row');
            $table->string('issue_code', 60);
            $table->text('message');
            $table->json('source_row_data')->nullable();
            $table->timestamps();
            $table->unique(['source_file', 'source_row', 'issue_code'], 'import_issue_unique');
        });
        Schema::create('master_import_rows', function (Blueprint $table) {
            $table->id();
            $table->string('source_file', 120);
            $table->unsignedInteger('source_row');
            $table->string('record_kind', 40);
            $table->string('source_fingerprint', 64);
            $table->json('source_data');
            $table->timestamps();
            $table->unique(['source_file', 'source_row']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_import_rows');
        Schema::dropIfExists('master_import_issues');
        Schema::dropIfExists('user_business_units');
        Schema::table('product_registrations', fn (Blueprint $table) => $table->dropColumn([
            'source_registration_status', 'source_commercial_status', 'product_registration_type',
            'product_description_in_market', 'source_metadata',
        ]));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'uno_medical_code', 'other_erp_code', 'product_code_on_label',
            'global_commercial_status', 'source_metadata',
        ]));
        Schema::table('regions', fn (Blueprint $table) => $table->dropColumn('source_region_label'));
    }
};
