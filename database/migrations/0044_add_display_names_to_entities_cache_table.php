<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities_cache', function (Blueprint $table) {
            $table->string('display_name', 1024)->nullable()->after('name_bn');
            $table->string('display_name_bn', 1024)->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('entities_cache', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'display_name_bn']);
        });
    }
};
