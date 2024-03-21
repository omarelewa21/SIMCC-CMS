<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrityCheckCompetitionCountries extends Model
{
    use HasFactory;

    protected $table = 'competition_countries_for_integrity_check';

    protected $fillable = ['competition_id', 'country_id', 'is_computed', 'is_confirmed', 'confirmed_by', 'confirmed_at'];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function country()
    {
        return $this->belongsTo(Countries::class);
    }

    public static function booted()
    {
        parent::booted();

        static::saved(function($model) {
            if($model->wasChanged('is_confirmed')) {
                $model->update(['confirmed_by' => auth()->id(), 'confirmed_at' => now()]);
            }
        });
    }

    public static function updateCountriesComputeStatus(Competition $competition, array|null $countryIds)
    {
        if($countryIds) {
            self::whereIn('country_id', $countryIds)->where('competition_id', $competition->id)
                ->update(['is_computed' => true]);
        } else {
            self::where('competition_id', $competition->id)->update(['is_computed' => true]);
        }
    }

    public static function setCompetitionCountries(Competition $competition, array|null $countryIds)
    {
        $allCountries = Countries::getCompetitionCountryList($competition);
        $countriesCreated   = self::where('competition_id', $competition->id)->pluck('country_id')->toArray();
        $countriesToCreate = array_diff($allCountries, $countriesCreated);
        foreach($countriesToCreate as $countryId) {
            self::create(['competition_id' => $competition->id, 'country_id' => $countryId]);
        }

        if($countryIds) {
            self::whereIn('country_id', $countryIds)->where('competition_id', $competition->id)
                ->update(['is_computed' => false]);
        } else {
            self::where('competition_id', $competition->id)->update(['is_computed' => false]);
        }
    }

    public static function getComputedCountriesList(Competition $competition): string
    {
        return self::join('all_countries', 'competition_countries_for_integrity_check.country_id', '=', 'all_countries.id')
            ->where([
                'competition_countries_for_integrity_check.competition_id' => $competition->id,
                'competition_countries_for_integrity_check.is_computed' => true
            ])
            ->select('all_countries.display_name')
            ->get()
            ->pluck('display_name')
            ->unique()
            ->join(', ', ' and ');
    }

    public static function getRemainingCountriesList(Competition $competition): string
    {
        return self::join('all_countries', 'competition_countries_for_integrity_check.country_id', '=', 'all_countries.id')
            ->where([
                'competition_countries_for_integrity_check.competition_id' => $competition->id,
                'competition_countries_for_integrity_check.is_computed' => false
            ])
            ->select('all_countries.display_name')
            ->get()
            ->pluck('display_name')
            ->unique()
            ->join(', ', ' and ');
    }
}
