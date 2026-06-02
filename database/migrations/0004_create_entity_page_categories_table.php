<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entity_page_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')
                  ->constrained('entity_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('category_name', 240);
            $table->string('category_slug', 240);
            $table->boolean('is_menu')->default(false);
            $table->string('menu_text', 240)->nullable();
            $table->integer('menu_order')->default(0);
            $table->text('link_url')->nullable()->comment('If this is a menu item, this can directly link to an URL');
            $table->timestamps();

            $table->unique(['entity_id', 'category_slug']);
            $table->unique(['entity_id', 'category_name']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('entity_page_categories');
    }
};
