<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->text('post_title');
            $table->text('post_excerpt')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->string('category', 240)->nullable()->comment("Legacy field - use category_id instead");
            $table->mediumText('post_content');
            $table->string('featured_image_uri', 1024)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('owner_entity_id');
            $table->string('author', 240)->nullable();
            $table->string('tags', 1024)->nullable();
            $table->enum('post_status', ['Draft', 'Published', 'Withdrawn'])->default('Draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('content_last_edited_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['category_id', 'post_status', 'is_featured']);
            $table->index('published_at');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('owner_entity_id')
                  ->references('id')
                  ->on('entity_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('posts');
    }
};
