<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Researcher extends Model
{
    protected $table = 'researchers';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'rpid',
        'alternative_author_names',
        'research_interests',
    ];

    public function externalProfiles()
    {
        return $this->hasMany(ResearcherExternalProfile::class, 'reseacher_id');
    }
}
