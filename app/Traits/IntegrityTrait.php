<?php

namespace App\Traits;

use App\Models\Competition;
use App\Models\Countries;

trait IntegrityTrait
{
    protected function hasConfirmedCountry(Competition $competition, array $countryIds): array|false
    {
        $countries = $competition->integrityCheckCountries()
            ->whereIn('country_id', $countryIds)
            ->where('is_confirmed', 1)
            ->join('all_countries', 'all_countries.id', '=', 'competition_countries_for_integrity_check.country_id')
            ->select('all_countries.display_name as name')
            ->get();

        if($countries->isEmpty()) return false;

        return $countries->pluck('name')->toArray();
    }

    protected function hasParticipantBelongsToConfirmedCountry(Competition $competition, array $participantIndexes): array|false
    {
        $countries = $competition->participants()
            ->whereIn('index_no', $participantIndexes)
            ->join('competition_countries_for_integrity_check', 'competition_countries_for_integrity_check.country_id', '=', 'participants.country_id')
            ->join('all_countries', 'all_countries.id', '=', 'competition_countries_for_integrity_check.country_id')
            ->where('competition_countries_for_integrity_check.is_confirmed', 1)
            ->where('competition_countries_for_integrity_check.competition_id', $competition->id)
            ->select('all_countries.display_name as name')
            ->distinct()
            ->get();

        if($countries->isEmpty()) return false;

        return $countries->pluck('name')->toArray();
    }

    protected function getCountriesWithNoAnswersUploaded(Competition $competition, array $countryIds): array|false
    {
        $countries = Countries::whereIn('all_countries.id', $countryIds)
            ->leftJoin('participants', 'participants.country_id', 'all_countries.id')
            ->leftJoin('participant_answers', 'participant_answers.participant_index', 'participants.index_no')
            ->leftJoin('competition_organization', 'competition_organization.id', 'participants.competition_organization_id')
            ->where('competition_organization.competition_id', $competition->id)
            ->selectRaw("COUNT(participant_answers.id) as total_answers, all_countries.display_name as name")
            ->groupBy('all_countries.display_name')
            ->having('total_answers', 0)
            ->get();

        if($countries->isEmpty()) return false;
        return $countries->pluck('name')->toArray();
    }
}
