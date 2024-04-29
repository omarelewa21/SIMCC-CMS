<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Countries extends Model
{
    use HasFactory;

    protected $table = "all_countries";
    public $timestamps = false;

    public function schools () {
        return $this->hasMany(School::class,"country_id");
    }

    public function participants()
    {
        return $this->hasMany(Participants::class, 'country_id');
    }

    public static function getCompetitionCountryList(Competition $competition): array
    {
        return $competition->participants()
            ->select('participants.country_id')
            ->distinct()
            ->get()
            ->pluck('country_id')
            ->toArray();
    }
}
