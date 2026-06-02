<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnel_additional_data', function (Blueprint $table) {
            $table->id();
            $table->char('personnel_id', 26);
            $table->string('data_group', 240)->nullable();
            $table->string('data_key', 240);
            $table->text('value');
            $table->enum('value_type', ['string','int','float','bool','json'])->default('string');
            $table->timestamps();

            $table->foreign('personnel_id')
                  ->references('personnel_id')
                  ->on('personnel_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnel_additional_data');
    }
};
