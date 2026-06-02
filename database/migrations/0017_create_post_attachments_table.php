<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('post_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')
                  ->constrained('posts')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('attachment_title', 240)->nullable();
            $table->text('attachment_uri');
            $table->string('attachment_type', 50)->nullable()->comment('document, image, video, other');
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('File size in bytes');
            $table->string('mime_type', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['post_id', 'attachment_type']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('post_attachments');
    }
};
