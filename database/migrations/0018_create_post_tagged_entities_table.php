<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('post_tagged_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')
                  ->constrained('posts')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->foreignId('entity_id')
                  ->comment('The entity that is tagged in this post')
                  ->constrained('entity_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->enum('status', ['Pending', 'Approved', 'Denied', 'Withdrawn'])->default('Pending');
            $table->string('approved_by', 240)->nullable();
            $table->boolean('is_featured_override')
                  ->nullable()
                  ->comment('Null inherits the source post featured flag; true/false overrides it for the tagged entity.');
            $table->timestamps();
            
            // Unique constraint - can't tag the same entity twice in one post
            $table->unique(['post_id', 'entity_id']);
            
            // Index for querying tagged posts
            $table->index(['entity_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('post_tagged_entities');
    }
};
