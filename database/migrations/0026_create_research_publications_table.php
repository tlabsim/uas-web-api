<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('research_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')
                  ->constrained('researches')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->foreignId('publication_id')
                  ->constrained('publications')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->text('publication_title')->nullable(); // Note: field name has a space
            $table->text('publication_link')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['research_id', 'publication_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('research_publications');
    }
};
