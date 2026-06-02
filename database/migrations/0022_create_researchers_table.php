<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('researchers', function (Blueprint $table) {
            $table->id();
            $table->string('rpid', 50)->nullable()->unique()->comment('The string ID in specific format for easier identification, like an ORCID!');
            $table->text('alternative_author_names')->nullable();
            $table->text('research_interests')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('researchers');
    }
};
