<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('personnels_cache', function (Blueprint $table) {
            $table->string('designation_name', 240)->nullable()->after('designation');
            $table->string('designation_with_grade', 240)->nullable()->after('designation_name');
        });
    }

    public function down(): void {
        Schema::table('personnels_cache', function (Blueprint $table) {
            $table->dropColumn(['designation_with_grade', 'designation_name']);
        });
    }
};
