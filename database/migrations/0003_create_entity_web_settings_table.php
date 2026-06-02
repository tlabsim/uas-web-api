<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entity_web_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')
                  ->constrained('entity_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('key_group', 240)->nullable();
            $table->string('setting_key', 240);
            $table->text('value');
            $table->enum('value_type', ['string', 'int', 'float', 'bool', 'json'])->default('string');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('entity_web_settings');
    }
};
