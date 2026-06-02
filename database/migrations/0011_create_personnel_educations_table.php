<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnel_educations', function (Blueprint $table) {
            $table->id();
            $table->char('personnel_id', 26);
            $table->string('degree_title', 512);
            $table->enum('degree_level', [
                'Primary', 'Secondary', 'Higher Secondary', 
                'Undergraduate', 'Post-graduate', 'Doctoral', 'Post-doctoral'
            ]);
            $table->string('institution', 255);
            $table->string('awarding_body', 255)->nullable();
            $table->date('start_month_year');
            $table->date('end_month_year');
            $table->string('passing_year', 4);
            $table->timestamps();

            $table->foreign('personnel_id')
                  ->references('personnel_id')
                  ->on('personnel_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnel_educations');
    }
};
