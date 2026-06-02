<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('researcher_external_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('researcher_id')
                  ->constrained('researchers')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('profile_type', 240);
            $table->string('profile_id', 1024)->nullable();
            $table->text('profile_link');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('researcher_external_profiles');
    }
};
