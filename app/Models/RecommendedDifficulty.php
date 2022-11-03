<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class recommendedDifficulty extends Base
{
    use HasFactory;

    protected $table = 'recommended_difficulty';
    protected $guarded = [];
    protected $hidden=['id','gradeDifficulty_type','gradeDifficulty_id'];
    public  $timestamps = false;

    public function gradeDifficulty () {
       return $this->MorphTo();
    }
}
