<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personnel_affiliations_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_affiliation_id')->unique();
            $table->char('personnel_id', 26);
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_name', 1024)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('affiliation_type', 120)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('personnel_id')
                ->references('personnel_id')
                ->on('personnel_profiles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('entity_id')
                ->references('entity_id')
                ->on('entity_profiles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->index(['entity_id', 'is_active', 'is_primary'], 'pac_entity_active_primary_idx');
            $table->index(['personnel_id', 'is_active'], 'pac_personnel_active_idx');
            $table->index(['entity_id', 'affiliation_type'], 'pac_entity_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_affiliations_cache');
    }
};
