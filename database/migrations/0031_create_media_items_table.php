<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_entity_id')->comment('Entity that owns this media asset.');
            $table->unsignedBigInteger('folder_id')->nullable()->comment('Logical organizer folder for the media item.');
            $table->string('storage_disk', 40)->default('public');
            $table->string('storage_path', 255);
            $table->string('storage_context', 80)->nullable()->comment('Upload context such as posts, pages, gallery, etc.');
            $table->string('file_name', 255)->comment('Stored file name.');
            $table->string('original_name', 255);
            $table->string('public_url', 500)->nullable();
            $table->enum('media_type', ['image', 'video', 'document', 'other'])->default('image');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title', 255)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by_ims_user_id')->nullable();
            $table->string('uploaded_by_name', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_entity_id')
                ->references('entity_id')
                ->on('entity_profiles')
                ->cascadeOnDelete();
            $table->foreign('folder_id')
                ->references('id')
                ->on('media_folders')
                ->nullOnDelete();

            $table->index(['owner_entity_id', 'folder_id']);
            $table->index(['owner_entity_id', 'media_type']);
            $table->index(['owner_entity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
