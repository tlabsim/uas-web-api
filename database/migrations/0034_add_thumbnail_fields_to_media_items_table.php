<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->string('thumbnail_path', 255)
                ->nullable()
                ->after('public_url')
                ->comment('Stored thumbnail path for efficient media-library previews.');
            $table->string('thumbnail_url', 500)
                ->nullable()
                ->after('thumbnail_path')
                ->comment('Public thumbnail URL when available.');
            $table->unsignedInteger('thumbnail_width')
                ->nullable()
                ->after('height')
                ->comment('Generated thumbnail width in pixels.');
            $table->unsignedInteger('thumbnail_height')
                ->nullable()
                ->after('thumbnail_width')
                ->comment('Generated thumbnail height in pixels.');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn([
                'thumbnail_path',
                'thumbnail_url',
                'thumbnail_width',
                'thumbnail_height',
            ]);
        });
    }
};
