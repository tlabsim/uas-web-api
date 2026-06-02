<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('snippets', function (Blueprint $table) {
            $table->id();
            $table->string('snippet_group', 240)->nullable();
            $table->string('slug', 50)->unique();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name', 240);
            $table->mediumText('content');
            $table->text('tags')->nullable();
            $table->enum('status', ['Draft', 'Published'])->default('Draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('content_last_edited_at')->nullable();            
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    public function down(): void {
        Schema::dropIfExists('snippets');
    }
};
