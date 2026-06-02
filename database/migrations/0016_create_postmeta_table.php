<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('postmeta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('meta_key', 240);
            $table->mediumText('meta_value')->nullable();
            $table->enum('value_type', ['string', 'int', 'float', 'bool', 'date', 'datetime', 'json'])->default('string');
            $table->boolean('is_searchable')->default(true)->comment('Whether this field should be searchable');
            $table->timestamps();
            $table->softDeletes();

            // Composite index for efficient lookups
            $table->index(['post_id', 'meta_key']);
            $table->index(['meta_key', 'is_searchable']);
            $table->unique(['post_id', 'meta_key']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('postmeta');
    }
};
