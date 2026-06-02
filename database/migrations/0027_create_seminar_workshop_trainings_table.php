<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('seminar_workshop_trainings', function (Blueprint $table) {
            $table->id();
            $table->char('personnel_id', 26);
            $table->set('attendee_type', [
                'Organizer','Keynote speaker','Speaker','Presenter',
                'Panel discussant','Trainer','Trainee','Conductor','Attendee'
            ])->default('Attendee');
            $table->enum('type', ['Seminar','Workshop','Training']);
            $table->text('title');
            $table->text('excerpt')->nullable();
            $table->mediumText('description');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->foreign('personnel_id')
                  ->references('personnel_id')
                  ->on('personnel_profiles')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('seminar_workshop_trainings');
    }
};
