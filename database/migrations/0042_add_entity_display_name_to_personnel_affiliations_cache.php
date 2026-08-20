<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('personnel_affiliations_cache', function (Blueprint $table) {
            $table->string('entity_display_name', 384)->nullable()->after('entity_name');
        });
    }

    public function down(): void {
        Schema::table('personnel_affiliations_cache', function (Blueprint $table) {
            $table->dropColumn('entity_display_name');
        });
    }
};
