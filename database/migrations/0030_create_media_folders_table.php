<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_entity_id')->comment('Entity that owns this curator folder.');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Parent folder for nested organization.');
            $table->string('folder_name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_entity_id')
                ->references('entity_id')
                ->on('entity_profiles')
                ->cascadeOnDelete();
            $table->foreign('parent_id')
                ->references('id')
                ->on('media_folders')
                ->nullOnDelete();

            $table->unique(['owner_entity_id', 'parent_id', 'slug'], 'media_folders_owner_parent_slug_unique');
            $table->index(['owner_entity_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};
