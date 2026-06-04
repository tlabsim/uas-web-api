<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->string('public_key', 40)
                ->nullable()
                ->after('owner_entity_id')
                ->comment('Opaque stable public identifier for switchable media URL obfuscation.');

            $table->unique('public_key');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropUnique(['public_key']);
            $table->dropColumn('public_key');
        });
    }
};
