<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_program_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('ims_program_id');
            $table->string('slug', 180)->nullable();
            $table->string('display_title', 255)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('hero_media_item_id')->nullable();
            $table->longText('overview')->nullable();
            $table->longText('learning_outcomes')->nullable();
            $table->longText('admission_requirements')->nullable();
            $table->longText('curriculum')->nullable();
            $table->longText('career_opportunities')->nullable();
            $table->longText('fees_and_funding')->nullable();
            $table->string('accreditation', 500)->nullable();
            $table->string('application_label', 100)->nullable();
            $table->string('application_url', 1000)->nullable();
            $table->string('brochure_url', 1000)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 100)->nullable();
            $table->json('custom_sections')->nullable();
            $table->enum('status', ['Draft', 'Published'])->default('Draft');
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('entity_id')
                ->references('entity_id')
                ->on('entity_profiles')
                ->cascadeOnDelete();
            $table->foreign('hero_media_item_id')
                ->references('id')
                ->on('media_items')
                ->nullOnDelete();

            $table->unique(['entity_id', 'ims_program_id'], 'epp_entity_program_uq');
            $table->unique(['entity_id', 'slug'], 'epp_entity_slug_uq');
            $table->index(['entity_id', 'status', 'is_visible'], 'epp_public_idx');
            $table->index(['entity_id', 'sort_order'], 'epp_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_program_profiles');
    }
};
