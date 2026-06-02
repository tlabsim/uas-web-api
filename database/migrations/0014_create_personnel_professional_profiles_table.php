<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnel_professional_profiles', function (Blueprint $table) {
            $table->id();
            $table->char('personnel_id', 26);
            $table->string('profile_type', 255);
            $table->string('profile_link', 1024);
            $table->timestamps();

            $table->foreign('personnel_id')
                  ->references('personnel_id')
                  ->on('personnel_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnel_professional_profiles');
    }
};
