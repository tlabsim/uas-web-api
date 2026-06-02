<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeminarWorkshopTraining extends Model
{
    protected $table = 'seminar_workshop_trainings';

    public $timestamps = true;

    protected $fillable = [
        'personnel_id',
        'attendee_type',
        'type',
        'title',
        'excerpt',
        'description',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'attendee_type' => 'string', 
        'type' => 'string', 
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
