<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_attachments', function (Blueprint $table) {
            $table->string('storage_bucket', 64)
                ->nullable()
                ->after('public_key')
                ->comment('Opaque storage folder bucket for direct public file serving.');
            $table->string('storage_suffix_key', 16)
                ->nullable()
                ->after('storage_bucket')
                ->comment('Stable suffix appended to the original filename for deterministic storage names.');

            $table->index('storage_bucket');
            $table->unique('storage_suffix_key');
        });
    }

    public function down(): void
    {
        Schema::table('post_attachments', function (Blueprint $table) {
            $table->dropUnique(['storage_suffix_key']);
            $table->dropIndex(['storage_bucket']);
            $table->dropColumn([
                'storage_bucket',
                'storage_suffix_key',
            ]);
        });
    }
};
