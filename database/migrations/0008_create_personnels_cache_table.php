<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnels_cache', function (Blueprint $table) {
            $table->char('personnel_id', 26)->primary();
            $table->enum('personnel_type', ['Teacher','Executive','Officer','Staff','Associate','Other']);
            $table->string('title', 50)->nullable();
            $table->string('title_bn', 50)->nullable();
            $table->string('first_name', 240);
            $table->string('first_name_bn', 240);
            $table->string('last_name', 240)->nullable();
            $table->string('last_name_bn', 240)->nullable();
            $table->enum('sex', ['Male','Female','Other']);
            $table->string('designation', 240);
            $table->string('pin', 20)->nullable();
            $table->integer('seniority_order')->nullable();
            $table->string('institutional_mail', 240)->nullable();
            $table->string('primary_phone', 240)->nullable();
            $table->text('photo_url')->nullable();
            $table->string('employment_type', 240)->nullable();
            $table->date('date_of_joining')->nullable();
            $table->string('status', 240)->nullable();
            $table->timestamps();

            //Add FK personnel_id to personnel_profiles table
            $table->foreign('personnel_id')
                  ->references('personnel_id')
                  ->on('personnel_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnels_cache');
    }
};
