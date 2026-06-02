<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('publication_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('author_name', 240);
            $table->unsignedBigInteger('internal_author_id')->nullable();
            $table->text('external_author_profile_link')->nullable();
            $table->unsignedInteger('sl');
            $table->boolean('is_primary_editor')->default(false);
            $table->boolean('is_editor')->default(true);
            $table->boolean('show_in_profile')->default(false);
            $table->timestamps();

            // Consider FK to publications if needed
        });
    }

    public function down(): void {
        Schema::dropIfExists('publication_authors');
    }
};
