<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable()->comment('Icon class or identifier');
            $table->boolean('is_system')->default(false)->comment('System categories cannot be deleted');
            $table->json('meta_schema')->nullable()->comment('JSON schema for required and extra metadata fields');
            $table->json('attachment_config')->nullable()->comment('Attachment requirements config');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['slug', 'is_active']);
            $table->index('is_system');
        });
    }

    public function down(): void {
        Schema::dropIfExists('post_categories');
    }
};
