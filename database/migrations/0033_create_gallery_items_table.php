<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gallery_id');
            $table->unsignedBigInteger('media_item_id');
            $table->string('caption_override', 500)->nullable();
            $table->string('alt_override', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('gallery_id')
                ->references('id')
                ->on('galleries')
                ->cascadeOnDelete();
            $table->foreign('media_item_id')
                ->references('id')
                ->on('media_items')
                ->cascadeOnDelete();

            $table->unique(['gallery_id', 'media_item_id']);
            $table->index(['gallery_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
    }
};
