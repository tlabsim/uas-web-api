<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelAchievement extends Model
{
    protected $table = 'personnel_achievements';

    public $timestamps = true;

    protected $fillable = [
        'personnel_id',
        'type',
        'title',
        'awarding_body',
        'award_date',
        'excerpt',
    ];

    protected $casts = [
        'type' => 'string',
        'award_date' => 'date',
        
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
