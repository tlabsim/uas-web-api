<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnel_profiles', function (Blueprint $table) {
            $table->char('personnel_id', 26)->primary();
            $table->string('display_name', 240)->nullable();
            $table->string('display_designation', 240)->nullable();
            $table->text('short_bio')->nullable();
            $table->mediumText('biography')->nullable();
            $table->foreignId('researcher_id')
                  ->nullable()                  
                  ->constrained('researchers', 'id')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnel_profiles');
    }
};
