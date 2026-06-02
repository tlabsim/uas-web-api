<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entity_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('entity_id')->primary()->comment('Refers to entity_id in entities table of core DB');            
            $table->date('establishment_date')->nullable();
                        
            // The following two fields refer to core DB personnels and roles to identity the head of the entity
            $table->string('head_personnel_id')->nullable()->comment('Refers to a personnel ID in the personnels table of core DB');
            $table->unsignedBigInteger('head_role_assignment_id')->nullable()->comment('Refers to a role assignment ID in the entity_role_assignments table of IMS core DB');
            $table->string('head_role_name', 240)->nullable()->comment('Role name of the head of the entity - Chairperson, Director, Dean, etc.');

            // The following fields are cached copies of head info for quick access, also to retain info if core DB is unavailable
            $table->string('head_info_name', 240)->nullable();
            $table->string('head_info_designation', 240)->nullable()->comment('Official designation of the head of the entity - Professor, Associate Professor, etc.');
            $table->text('head_info_photo_url')->nullable();

            // Message from the head of the entity, to be shown on entity website
            $table->text('head_message')->nullable();
            
            // Slug for URL identification
            $table->string('slug', 50)->unique();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('entity_profiles');
    }
};
