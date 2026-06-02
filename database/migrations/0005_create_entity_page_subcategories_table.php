<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entity_page_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('entity_page_categories') 
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('subcategory_name', 240);
            $table->string('subcategory_slug', 240);
            $table->boolean('is_menu')->default(false);
            $table->string('menu_text', 240)->nullable();
            $table->integer('menu_order')->default(0);
            $table->text('link_url')->nullable()->comment('If this is a menu item, this can directly link to an URL');
            $table->timestamps();

            $table->unique(['category_id', 'subcategory_name']);
            $table->unique(['category_id', 'subcategory_slug']);            
        });
    }

    public function down(): void {
        Schema::dropIfExists('entity_page_subcategories');
    }
};
