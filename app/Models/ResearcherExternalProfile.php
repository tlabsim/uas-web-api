<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearcherExternalProfile extends Model
{
    protected $table = 'researcher_external_profiles';

    public $timestamps = true;

    protected $fillable = [
        'reseacher_id', // note: typo in original schema
        'profile_type',
        'profile_id',
        'profile_link',
    ];

    public function researcher()
    {
        return $this->belongsTo(Researcher::class, 'reseacher_id');
    }
}
