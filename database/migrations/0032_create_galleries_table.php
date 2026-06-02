<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_entity_id')->comment('Entity that owns this gallery.');
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->string('excerpt', 500)->nullable();
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('cover_media_item_id')->nullable();
            $table->enum('gallery_status', ['Draft', 'Published', 'Withdrawn'])->default('Draft');
            $table->boolean('is_featured')->default(false);
            $table->string('author', 240)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('content_last_edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_entity_id')
                ->references('entity_id')
                ->on('entity_profiles')
                ->cascadeOnDelete();
            $table->foreign('cover_media_item_id')
                ->references('id')
                ->on('media_items')
                ->nullOnDelete();

            $table->unique(['owner_entity_id', 'slug']);
            $table->index(['owner_entity_id', 'gallery_status', 'published_at']);
            $table->index(['owner_entity_id', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
