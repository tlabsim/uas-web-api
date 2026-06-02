<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('snippetmeta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snippet_id')
                  ->constrained('snippets')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('meta_key', 240);
            $table->mediumText('meta_value');
            $table->enum('value_type', ['string','int','float','bool','json'])->default('string');
            $table->timestamps();
            $table->softDeletes(); 

        });
    }

    public function down(): void {
        Schema::dropIfExists('snippetmeta');
    }
};
