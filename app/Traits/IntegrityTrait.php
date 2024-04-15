<?php

namespace App\Traits;

use App\Models\Competition;

trait IntegrityTrait
{
    protected function hasConfirmedCountry(Competition $competition, array $countryIds): array|false
    {
        $countryIds = $competition->integrityCheckCountries()
            ->whereIn('country_id', $countryIds)
            ->where('is_confirmed', 1)
            ->join('all_countries', 'all_countries.id', '=', 'competition_countries_for_integrity_check.country_id')
            ->select('country_id', 'display_name as name')
            ->get();

        if($countryIds->isEmpty()) return false;

        return $countryIds->pluck('name')->toArray();
    }
}