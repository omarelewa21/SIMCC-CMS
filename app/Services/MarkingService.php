<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionRounds;
use App\Models\Countries;
use App\Models\FlagNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MarkingService
{
    /**
     * Get mark list
     *
     * @param \App\Models\Competition $competition
     *
     * @return array
     */
    public function markList(Competition $competition)
    {
        $competition->load(
            ['rounds.levels' => ['levelGroupComputes', 'markingLogs', 'collection.sections', 'rounds']],
            'groups.countries:id,display_name'
        );
        $countryGroups = $this->getListCompetitionGroupsWithCountries($competition);
        $rounds = $competition->rounds
            ->mapWithKeys(function ($round) use ($countryGroups) {
                return [$round['name'] => $this->getLevelList($round, $countryGroups)];
            });

        return [
            "competition_name" => $competition['name'],
            "notification_count" => $competition->flagNotifications()->count(),
            "rounds"           => $rounds
        ];
    }

    /**
     * Get list of competition groups with countries
     * @param \App\Models\Competition $competition
     * @return \Illuminate\Support\Collection
     */
    private function getListCompetitionGroupsWithCountries(Competition $competition): Collection
    {
        return $competition->groups
            ->pluck('countries', 'id')
            ->mapWithKeys(function ($countryGroup, $group_id) {
                return [$group_id => $countryGroup->pluck('display_name', 'id')];
            });
    }

    /**
     * Get Marking level list
     * @param \App\Models\CompetitionRounds $round
     * @param \Illuminate\Support\Collection $countryGroups
     * @return \Illuminate\Support\Collection
     */
    private function getLevelList(CompetitionRounds $round, Collection $countryGroups): Collection
    {
        return $round->levels->mapWithKeys(function ($level) use ($countryGroups) {
            $totalParticipants = $this->getLevelTotalParticipants($level);
            $answersUploaded = $this->getLevelAnswersUploaded($level);
            $markedAnswers = $this->getLevelMarkedAnswers($level);
            $absentees = $this->getLevelAbsentees($level);
            $isLevelReadyToCompute = $this->isLevelReadyToCompute($level);
            $flagNotifications = $level->flagNotifications->where('type', FlagNotification::TYPE_RECOMPUTE)->groupBy('group_id');

            $levels = [];
            foreach ($countryGroups as $group_id => $countryGroup) {
                $countryGroupIds = $countryGroup->keys()->toArray();

                $totalParticipantsCount = $totalParticipants->whereIn('country_id', $countryGroupIds)->sum('total_participants');
                $markedAnswersCount = $markedAnswers->whereIn('country_id', $countryGroupIds)->sum('marked_participants');
                $absentees = $absentees->whereIn('country_id', $countryGroupIds);
                $answersUploadedCount = $answersUploaded->whereIn('country_id', $countryGroupIds)->sum('answers_uploaded');
                $levelGroupCompute = $level->levelGroupComputes->where('group_id', $group_id)->first();
                $logs = $level->markingLogs->filter(fn($log) => $log->group_id == $group_id);
                $firstLogs = $logs->first();
                $recompute_required = $flagNotifications->has($group_id);
                $levels[$level->id][] = [
                    'level_id'                      => $level->id,
                    'name'                          => $level->name,
                    'level_is_ready_to_compute'     => $isLevelReadyToCompute && $answersUploadedCount > 0,
                    'computing_status'              => $levelGroupCompute?->computing_status ?? 'Not Started',
                    'compute_progress_percentage'   => $levelGroupCompute?->compute_progress_percentage ?? 0,
                    'compute_error_message'         => $levelGroupCompute?->compute_error_message ?? null,
                    'moderation_status'             => $levelGroupCompute?->awards_moderated ?? 0,
                    'total_participants'            => $totalParticipantsCount,
                    'answers_uploaded'              => $answersUploadedCount,
                    'marked_participants'           => $markedAnswersCount,
                    'absentees_count'               => $absentees->count(),
                    'absentees'                     => $absentees->count() > 10 ? $absentees->random(10)->pluck('name') : $absentees->pluck('name'),
                    'country_group'                 => $countryGroup->values()->toArray(),
                    'marking_group_id'              => $group_id,
                    'computed_at'                   => $firstLogs?->computed_at->format('Y-m-d'),
                    'computed_by'                   => $firstLogs?->computed_by,
                    'logs'                          => $logs->values(),
                    'recompute_required' => $recompute_required,
                ];
            }
            return $levels;
        });
    }

    private function getLevelAbsentees(CompetitionLevels $level): Collection
    {
        return $level->participants()
            ->where('participants.status', 'absent')
            ->select('participants.name', 'participants.country_id')
            ->distinct()
            ->get();
    }

    /**
     * Get total participants for level
     * @param \App\Models\CompetitionLevels $level
     * @return \Illuminate\Support\Collection
     */
    private function getLevelTotalParticipants(CompetitionLevels $level): Collection
    {
        return $level->participants()
            ->groupBy('participants.country_id')
            ->selectRaw('participants.country_id, count(participants.id) as total_participants')
            ->get();
    }

    /**
     * Get marked participants for level
     * @param \App\Models\CompetitionLevels $level
     * @return \Illuminate\Support\Collection
     */
    private function getLevelMarkedAnswers(CompetitionLevels $level): Collection
    {
        return $level->participantsAnswersUploaded()
            ->join('participants', 'participants.index_no', 'participant_answers.participant_index')
            ->where('participants.status', 'result computed')
            ->groupBy('participants.country_id')
            ->selectRaw('participants.country_id, count(participants.id) as marked_participants')
            ->get();
    }

    /**
     * Get answers uploaded for level
     * @param \App\Models\CompetitionLevels $level
     * @return \Illuminate\Support\Collection
     */
    private function getLevelAnswersUploaded(CompetitionLevels $level): Collection
    {
        return $level->participantsAnswersUploaded()
            ->join('participants', 'participants.index_no', 'participant_answers.participant_index')
            ->groupBy('participants.country_id')
            ->selectRaw('participants.country_id, count(participants.id) as answers_uploaded')
            ->get();
    }

    /**
     * Get absentees for country group
     * @param \App\Models\CompetitionLevels $level
     * @param array $countryGroupIds
     * @return \Illuminate\Support\Collection
     */
    private function getLevelAbsenteesForCountryGroup(CompetitionLevels $level, array $countryGroupIds)
    {
        return $level->participants()
            ->where('participants.status', 'absent')
            ->whereIn('participants.country_id', $countryGroupIds)
            ->select('participants.name')
            ->distinct()
            ->get();
    }

    /**
     * Check if competition are ready for computing
     *
     * @param App\Models\Competition $competition
     *
     * @return bool
     */
    public function isCompetitionReadyForCompute(Competition $competition)
    {
        foreach ($competition->rounds as $round) {
            foreach ($round->levels as $level) {
                if (!$this->isLevelReadyToCompute($level)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * check if level is ready for computing - returns true if (all tasks has corresponding true answers and level has uploaded answers)
     *
     * @param \App\Models\CompetitionLevels $level
     * @return bool
     */
    public static function isLevelReadyToCompute(CompetitionLevels $level)
    {
        if ($level->rounds->roundsAwards()->doesntExist()) return false;

        $levelTaskIds = $level->collection->sections
            ->pluck('section_task')->flatten()->pluck("id");

        $numberOfTasksIds = $levelTaskIds->count('count_tasks');

        $numberOfCorrectAnswersWithMarks = $level->taskMarks()->join('task_answers', function ($join) {
            $join->on('competition_tasks_mark.task_answers_id', 'task_answers.id')->whereNotNull('task_answers.answer');
        })
            ->whereIn('task_answers.task_id', $levelTaskIds)
            ->select('task_answers.task_id')->distinct()->count();

        return $numberOfCorrectAnswersWithMarks >= $numberOfTasksIds;

        // Log::info(sprintf("%s: %s %s %s %s", $level->id, $numberOfTasksIds, $numberOfCorrectAnswersWithMarks, $level->participantsAnswersUploaded()->count(), $level->rounds->roundsAwards()->count()));
    }

    /**
     * check if level is ready for computing - returns true if (all tasks has corresponding true answers and level has uploaded answers)
     *
     * @param \App\Models\CompetitionLevels $level
     * @param \App\Models\CompetitionMarkingGroup $group
     *
     * @return bool
     */
    public static function noAnswersUploadedForLevelAndGroup(CompetitionLevels $level, CompetitionMarkingGroup $group)
    {
        $countryIds = $group->countries->pluck('id')->toArray();
        return $level->participantsAnswersUploaded()
            ->join('participants', 'participants.index_no', 'participant_answers.participant_index')
            ->whereIn('participants.country_id', $countryIds)
            ->doesntExist();
    }

    /**
     * get cut off points for participant results
     *
     * @param Illuminate\Database\Eloquent\Collection $participantResults
     *
     * @return array
     */
    public function getCutOffPoints($participantResults)
    {
        $participantAwards = $participantResults->pluck('award')->unique();
        $data = [];
        foreach ($participantAwards as $award) {
            $filteredParticipants = $participantResults->filter(fn($participantResult) => $participantResult->award == $award);
            $data[$award]['max'] = $filteredParticipants->first()->points;
            $data[$award]['min'] = $filteredParticipants->last()->points;
        }
        return $data;
    }

    /**
     * get participants count (with and without answers) by country and grade
     *
     * @param App\Models\Competition $competition
     * @param Illuminate\Http\Request $request
     *
     * @return array
     */
    public static function getActiveParticipantsByCountryByGradeData(Competition $competition, Request $request)
    {
        $countries = Countries::whereIn('id', $request->countries)->select('id', 'display_name')
            ->get()
            ->mapWithKeys(fn($country) => [
                $country->id => [
                    'name' => $country->display_name,
                    'id'   => $country->id
                ]
            ]);

        $totalParticipants = $competition->participants()
            ->whereIn('participants.country_id', $request->countries)
            ->groupBy('participants.country_id', 'participants.grade')
            ->selectRaw('participants.country_id, participants.grade, count(participants.id) as total_participants')
            ->get();

        $totalParticipantsWithAnswer = $competition->participants()
            ->whereIn('participants.country_id', $request->countries)
            ->has('answers')
            ->groupBy('participants.country_id', 'participants.grade')
            ->selectRaw('participants.country_id, participants.grade, count(participants.id) as total_participants_with_answers')
            ->get();

        return [$countries, $totalParticipants, $totalParticipantsWithAnswer];
    }

    /**
     * get all participants count by country and grade
     *
     * @param array $data by reference
     * @param Illuminate\Database\Eloquent\Collection $countries
     * @param Illuminate\Database\Eloquent\Collection $totalParticipants
     *
     * @return void
     */
    public static function setTotalParticipantsByCountryByGrade(&$data, $countries, $totalParticipants)
    {
        foreach ($totalParticipants as $participants) {
            $country = $countries[$participants->country_id]['name'];

            static::inititializeCountryGrade($data, $participants->grade, $country);

            // Add total participants for each country and grade
            $data[$participants->grade][$country] = [
                'total_participants' => $participants->total_participants,
                'country'            => $country,
                'grade'              => $participants->grade
            ];

            // Add total participants for all grades per country
            $data['total'][$country]['total_participants'] += $participants->total_participants;

            // Add total participants for all countries per grade
            $data[$participants->grade]['total']['total_participants'] += $participants->total_participants;
        }
    }

    /**
     * get participants (with answers) and absentees count by country and grade
     *
     * @param array $data by reference
     * @param Illuminate\Database\Eloquent\Collection $countries
     * @param Illuminate\Database\Eloquent\Collection $totalParticipantsWithAnswer
     *
     * @return void
     */
    public static function setTotalParticipantsWithAnswersAndAbsentees(&$data, $countries, $totalParticipantsWithAnswer)
    {
        foreach ($totalParticipantsWithAnswer as $participants) {
            $country = $countries[$participants->country_id]['name'];
            // Add total participants with answers for each country and grade
            $data[$participants->grade][$country]['total_participants_with_answers']
                = $participants->total_participants_with_answers;

            // Add total absentees for each country and grade
            $data[$participants->grade][$country]['absentees']
                = $data[$participants->grade][$country]['total_participants'] - $participants->total_participants_with_answers;

            $data[$participants->grade]['total']['total_participants_with_answers'] += $participants->total_participants_with_answers;    // Add total participants with answers for all countries per grade
            $data[$participants->grade]['total']['absentees'] += $data[$participants->grade][$country]['absentees'];     // Add total absentees for all countries per grade

            // Add total participants for all grades per country
            $data['total'][$country]['total_participants_with_answers'] += $participants->total_participants_with_answers;
            $data['total'][$country]['absentees'] += $data[$participants->grade][$country]['absentees'];
        }
    }

    private static function inititializeCountryGrade(&$data, $grade, $country)
    {
        if (!isset($data[$grade]['total']['total_participants'])) {
            // Initialize total participants for all countries per grade
            $data[$grade]['total'] = [
                'total_participants' => 0,
                'total_participants_with_answers' => 0,
                'absentees' => 0,
                'grade'     => $grade
            ];
        }

        if (!isset($data['total'][$country])) {
            $data['total'][$country] = [
                'total_participants' => 0,
                'total_participants_with_answers' => 0,
                'absentees' => 0,
                'country'   => $country
            ];
        }
    }

    public static function adjustDataTotalToIncludeAllCountries(&$data, $countries)
    {
        $dataCountries = array_keys($data['total']);
        $requestCountries = $countries->pluck('name')->toArray();
        $missingCountries = array_diff($requestCountries, $dataCountries);
        foreach ($missingCountries as $country) {
            $data['total'][$country] = [
                'total_participants' => 0,
                'total_participants_with_answers' => 0,
                'absentees' => 0,
                'country'   => $country
            ];
        }
    }
}
