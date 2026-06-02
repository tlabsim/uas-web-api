<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnel_job_experiences', function (Blueprint $table) {
            $table->id();
            $table->char('personnel_id', 26);
            $table->string('job_title', 512);
            $table->string('role', 255);
            $table->mediumText('role_description')->nullable();
            $table->string('organization', 255)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->foreign('personnel_id')
                  ->references('personnel_id')
                  ->on('personnel_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnel_job_experiences');
    }
};
