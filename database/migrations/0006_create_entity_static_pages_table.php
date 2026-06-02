<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entity_static_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')
                  ->constrained('entity_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('page_slug', 240);
            $table->text('page_title');
            $table->text('page_excerpt')->nullable();
            $table->mediumText('page_content');
            $table->mediumText('custom_css')->nullable();
            $table->mediumText('custom_js')->nullable();
            $table->mediumText('featured_image_uri')->nullable();
            $table->unsignedBigInteger('page_category')->nullable();
            $table->unsignedBigInteger('page_subcategory')->nullable();
            $table->boolean('is_menu')->default(false);
            $table->string('menu_text', 100)->nullable();
            $table->integer('menu_order')->default(999)->comment('Order in navigation menu');
            $table->string('author', 240)->nullable();
            $table->enum('page_status', ['Draft', 'Published', 'Withdrawn'])->default('Draft');
            $table->timestamp('published_at')->nullable();            
            $table->timestamp('content_last_edited_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Unique constraint: slug must be unique per entity
            $table->unique(['entity_id', 'page_slug']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('entity_static_pages');
    }
};
