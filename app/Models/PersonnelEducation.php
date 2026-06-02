<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelEducation extends Model
{
    protected $table = 'personnel_educations';

    public $timestamps = true;

    protected $fillable = [
        'personnel_id',
        'degree_title',
        'degree_level',
        'institution',
        'awarding_body',
        'start_month_year',
        'end_month_year',
        'passing_year',
    ];

    protected $casts = [
        'start_month_year' => 'date',
        'end_month_year' => 'date',
    ];

    protected $appends = [
        'start_month',
        'start_year',
        'end_month',
        'end_year',
    ];


    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }

    // Separsately get start_month, start_year, end_month, end_year
    public function getStartMonthAttribute()
    {
        return $this->start_month_year ? $this->start_month_year->format('F') : null;
    }

    public function getStartYearAttribute()
    {
        return $this->start_month_year ? $this->start_month_year->format('Y') : null;
    }

    public function getEndMonthAttribute()
    {
        return $this->end_month_year ? $this->end_month_year->format('F') : null;
    }

    public function getEndYearAttribute()
    {
        return $this->end_month_year ? $this->end_month_year->format('Y') : null;
    }

}
