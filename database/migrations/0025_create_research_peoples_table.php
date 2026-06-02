<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('research_peoples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')
                  ->constrained('researches')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('researcher_name', 240);
            $table->foreignId('internal_researcher_id')
                  ->nullable()
                  ->constrained('researchers')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->text('external_researcher_profile_link')->nullable();
            $table->string('role', 240);
            $table->integer('sl')->default(1);
            $table->boolean('is_primary_editor')->default(false);
            $table->boolean('is_editor')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('research_peoples');
    }
};
