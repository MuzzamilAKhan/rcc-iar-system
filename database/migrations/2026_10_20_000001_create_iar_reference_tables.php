<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position', 150)->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('assignment_strategy', 32)->default('country_assessor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('country_assessors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('assignment_priority');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['country_id', 'assignment_priority']);
            $table->unique(['country_id', 'user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('country_assessors');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('regions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
            $table->dropColumn(['position', 'is_active']); });
        Schema::dropIfExists('business_units');
    }
};
