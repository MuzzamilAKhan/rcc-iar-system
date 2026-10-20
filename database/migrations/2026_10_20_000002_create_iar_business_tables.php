<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('sap_code', 100)->index(); $table->string('product_name');
            $table->string('brand',150)->nullable(); $table->string('icc_code',100)->nullable();
            $table->string('basic_udi',150)->nullable(); $table->string('gmdn_code',100)->nullable();
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dhf_number',150)->nullable(); $table->string('device_type',150)->nullable();
            $table->timestamps();
        });
        Schema::create('product_registrations', function (Blueprint $table) {
            $table->id(); $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('registration_number',150)->nullable(); $table->string('registration_status',50)->index();
            $table->date('initial_registration_date')->nullable(); $table->date('certificate_issue_date')->nullable();
            $table->date('certificate_expiry_date')->nullable(); $table->string('commercial_status',100)->nullable();
            $table->string('market_authorization_holder')->nullable(); $table->string('legal_manufacturer')->nullable();
            $table->string('physical_manufacturer')->nullable(); $table->string('importer')->nullable();
            $table->string('specification_developer')->nullable(); $table->string('device_classification',100)->nullable();
            $table->string('fda_510k_number_or_exempt',150)->nullable(); $table->string('fda_product_code',100)->nullable();
            $table->string('doc_number',150)->nullable(); $table->date('doc_approval_date')->nullable();
            $table->string('ce_certificate_number',150)->nullable(); $table->date('ce_certificate_issue_date')->nullable();
            $table->timestamps(); $table->index(['product_id','registration_status','country_id'],'reg_lookup_idx');
        });
        Schema::create('impact_assessments', function (Blueprint $table) {
            $table->id(); $table->string('iar_number',50)->unique(); $table->string('ccr_number',100)->index();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->text('current_version'); $table->text('new_version');
            $table->boolean('sap_udi_icc_impacted'); $table->boolean('labelling_impacted'); $table->boolean('is_urgent')->default(false);
            $table->date('estimated_implementation_date')->nullable(); $table->dateTime('due_at');
            $table->string('status',30)->default('draft'); $table->dateTime('submitted_at')->nullable(); $table->dateTime('closed_at')->nullable();
            $table->timestamps(); $table->index(['creator_id','status']);
        });
        Schema::create('iar_products', function (Blueprint $table) {
            $table->id(); $table->foreignId('impact_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('submitted_sap_code',100); $table->timestamps();
            $table->unique(['impact_assessment_id','submitted_sap_code'],'iar_sap_unique');
        });
        Schema::create('iar_assignments', function (Blueprint $table) {
            $table->id(); $table->foreignId('impact_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_source',30); $table->string('status',30)->default('assigned');
            $table->dateTime('due_at'); $table->dateTime('assigned_at')->nullable(); $table->dateTime('completed_at')->nullable();
            $table->timestamps(); $table->unique(['impact_assessment_id','country_id'],'iar_country_unique');
            $table->index(['assigned_to_user_id','status','due_at'],'assignee_queue_idx');
        });
        Schema::create('assessment_responses', function (Blueprint $table) {
            $table->id(); $table->foreignId('iar_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('impact_type',40)->nullable(); $table->text('timeline')->nullable();
            $table->longText('action_items')->nullable(); $table->longText('comments')->nullable();
            $table->boolean('stop_ship_required')->nullable(); $table->boolean('more_information_required')->nullable();
            $table->boolean('changed_product_imported')->nullable(); $table->string('status',30)->default('draft');
            $table->dateTime('submitted_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['assessment_responses','iar_assignments','iar_products','impact_assessments','product_registrations','products'] as $name) Schema::dropIfExists($name);
    }
};
