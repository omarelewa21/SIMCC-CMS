<?php

namespace App\Services;

use App\Models\CompetitionParticipantsResults;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionService
{
    /**
     * get query for competition report
     * 
     * @param int $competitionId
     * @return \Illuminate\Contracts\Database\Query\Builder $query
     */
    public static function getReportData(int $competitionId): Collection
    {
        return 
        CompetitionParticipantsResults::leftJoin('competition_levels', 'competition_levels.id', 'competition_participants_results.level_id')
            ->leftJoin('competition_rounds', 'competition_levels.round_id', 'competition_rounds.id')
            ->leftJoin('competition', 'competition.id', 'competition_rounds.competition_id')
            ->leftJoin('participants', 'participants.index_no', 'competition_participants_results.participant_index')
            ->leftJoin('schools', 'participants.school_id', 'schools.id')
            ->leftJoin('schools AS tuition_school', 'participants.tuition_centre_id', 'schools.id')
            ->leftJoin('all_countries', 'all_countries.id', 'participants.country_id')
            ->leftJoin('competition_organization', 'participants.competition_organization_id', 'competition_organization.id')
            ->leftJoin('organization', 'organization.id', 'competition_organization.organization_id')
            ->where('competition.id', $competitionId)
            ->select(DB::raw(
                "CONCAT('\"',competition.name,'\"') AS competition,
                CONCAT('\"',organization.name,'\"') AS organization,CONCAT('\"',all_countries.display_name,'\"') AS country,
                CONCAT('\"',competition_levels.name,'\"') AS level,
                participants.grade,
                CONCAT('\"',schools.name,'\"') AS school,
                CONCAT('\"',tuition_school.name,'\"') AS tuition_centre,
                participants.index_no,
                CONCAT('\"',participants.name,'\"') AS name,
                participants.certificate_no,
                competition_participants_results.points,
                competition_participants_results.country_rank,
                competition_participants_results.school_rank,
                competition_participants_results.global_rank,
                CONCAT('\"',competition_participants_results.award,'\"') AS award"
            ))
            ->orderBy("competition_levels.id")
            ->orderBy(DB::raw("FIELD(`competition_participants_results`.`award`,'PERFECT SCORER','GOLD','SILVER','BRONZE','HONORABLE MENTION','Participation')"))
            ->orderBy("competition_participants_results.points", "DESC")
            ->get();
    }
}
