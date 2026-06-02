<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('publication_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('meta_key', 240);
            $table->text('meta_value');
            $table->timestamps();

            // No foreign key defined in SQL, can be added if needed
        });
    }

    public function down(): void {
        Schema::dropIfExists('publication_meta');
    }
};
