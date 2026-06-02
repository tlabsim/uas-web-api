<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelJobExperience extends Model
{
    protected $table = 'personnel_job_experiences';

    public $timestamps = true;

    protected $fillable = [
        'personnel_id',
        'job_title',
        'role',
        'role_description',
        'organization',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
