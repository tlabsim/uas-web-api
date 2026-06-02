<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entities_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('entity_id')->primary();
            $table->string('entity_type', 240);
            $table->string('entity_category', 240);
            $table->unsignedBigInteger('parent_entity_id')->nullable();
            $table->string('parent_entity_name', 1024)->nullable();
            $table->string('title', 240)->nullable();            
            $table->string('title_bn', 240)->nullable();
            $table->string('name', 240);
            $table->string('name_bn', 240);
            $table->string('short_name', 240)->nullable();
            $table->string('short_name_bn', 240)->nullable();
            $table->text('description')->nullable();
            $table->text('logo_url')->nullable();
            $table->integer('entity_order')->default(0);
            $table->timestamps();

            $table->foreign('entity_id')
                  ->references('entity_id')
                  ->on('entity_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('entities_cache');
    }
};
