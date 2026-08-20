<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personnels_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_affiliation_entity_id')->nullable()->after('status');
            $table->string('primary_affiliation_name', 1024)->nullable()->after('primary_affiliation_entity_id');
            $table->string('primary_affiliation_type', 255)->nullable()->after('primary_affiliation_name');
            $table->timestamp('affiliations_cached_at')->nullable()->after('primary_affiliation_type');

            $table->index('primary_affiliation_entity_id', 'personnels_cache_primary_aff_entity_idx');
        });

        Schema::table('entities_cache', function (Blueprint $table) {
            $table->timestamp('teachers_cache_synced_at')->nullable()->after('entity_order');
        });
    }

    public function down(): void
    {
        Schema::table('entities_cache', function (Blueprint $table) {
            $table->dropColumn('teachers_cache_synced_at');
        });

        Schema::table('personnels_cache', function (Blueprint $table) {
            $table->dropIndex('personnels_cache_primary_aff_entity_idx');
            $table->dropColumn([
                'primary_affiliation_entity_id',
                'primary_affiliation_name',
                'primary_affiliation_type',
                'affiliations_cached_at',
            ]);
        });
    }
};
