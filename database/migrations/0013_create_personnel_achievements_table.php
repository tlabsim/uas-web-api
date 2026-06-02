<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnel_achievements', function (Blueprint $table) {
            $table->id();
            $table->char('personnel_id', 26);
            $table->enum('type', ['Award', 'Achievement']);
            $table->text('title');
            $table->text('awarding_body')->nullable();
            $table->date('award_date')->nullable();
            $table->text('excerpt')->nullable();
            $table->timestamps();

            $table->foreign('personnel_id')
                  ->references('personnel_id')
                  ->on('personnel_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnel_achievements');
    }
};
