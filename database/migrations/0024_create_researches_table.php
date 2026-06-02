<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('researches', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->text('excerpt')->nullable();
            $table->mediumText('description')->nullable();
            $table->text('featured_image_uri');
            $table->text('keywords');
            $table->enum('status', ['Ongoing', 'Completed'])->default('Ongoing');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('researches');
    }
};
