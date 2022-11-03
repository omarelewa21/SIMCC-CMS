<?php

namespace App\Custom;

use App\Models\Competition;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use App\Models\TasksAnswers;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Marking {

    function markList ($competition_id) {
        $competition = Competition::with(['competitionOrganization.participants:competition_organization_id,id,country_id,status','rounds.levels.taskMarks','rounds.levels.groups' => function($query) {
            $query->select(['id','competition_level_id','country_group','status']);
        },'rounds.levels.collection.sections'])->find($competition_id);

        $competition_name = $competition['name'];
        $participants = $competition['competitionOrganization']->pluck('participants')->flatten();

        $rounds = $competition['rounds']->mapWithKeys(function ($round) use($participants) {

            $round->levels->each(function ($level) use(&$levels,$participants){
                $level = $level->toArray();
                $numberOfTasksId = array_unique(Arr::flatten(Arr::pluck($level['collection']['sections'],'tasks')));
                $numberOfTasksWithMarksId = array_unique(Arr::pluck($level['task_marks'],'task_answers_id'));
                $numberOfCorrectAnswersWithMarks = TasksAnswers::whereIn('id',$numberOfTasksWithMarksId)->whereNotNull('answer')->get()->makeVisible(['task_id'])->groupBy('task_id');

                $levelAllTasksWithMarks = count($numberOfTasksId) === count($numberOfCorrectAnswersWithMarks) ? true : false;

                $groups = collect($level['groups'])->map(function ($group) use($participants) {
                    $absentee = Participants::whereIn('index_no',$group['particitpants_index_no_list'])->where('status','absent')->get()->flatten()->toArray();
//                    $marked = $participants->whereIn('country_id',$group['country_group'])->where('status','result computed')->toArray();
                    $marked = Participants::whereIn('index_no',$group['particitpants_index_no_list'])->where('status','result computed')->get()->toArray();

                    return [
                        'id' => $group['id'],
                        'country_name' => implode(",",$group['country_name']),
                        'total_participants' => $group['total_participants'],
                        'absentee' => $absentee,
                        'marked' => count($marked),
                        'status' => $group['status']
                    ];
                })->toArray();

                $levels[] = [
                    'level_id' => $level['id'],
                    'name' => $level['name'],
                    'level_ready' => $levelAllTasksWithMarks,
                    'groups' => $groups
                ];

            })->toArray();
            return [
                $round['name'] => $levels
            ];
        })->toArray();

        return ["competition_name" => $competition_name, "rounds" => $rounds];
    }

    function checkValidMarkingGroup ($group_id) {
        $groups = CompetitionMarkingGroup::find($group_id);
        $competition_id = $groups->level->rounds->competition->id;
        $competition = new Marking();
        $competition = $competition->markList($competition_id);
        $found = false;
        foreach ($competition['rounds'] as $round) {
            if(!$found) {
                foreach($round as $level) {
                    if($level['level_ready'] && !$found){
                        foreach($level['groups'] as $group) {
                            if(count($level['groups']) > 0 ){
                                if($group['id'] == $group_id && !$found) {
                                    $found = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $found;
    }

    function computingResults () {

        $groups = CompetitionMarkingGroup::where([
            'status' => 'computing'
        ])->orderBy('updated_at', 'asc');

        if($groups->count() == 0) {
            abort('200','no groups to compute');
        }

        $groups = $groups->first();

        $level_id = $groups->level->id;
        $rounds =  $groups->level->rounds;
        $default_award = $rounds->default_award_name;
        $awards = $rounds->roundsAwards->map(function ($award) {
            return [
                'ref_award_id' => $award['id'],
                'name' => $award['name'],
                'min_points' => $award['min_marks'],
                'percentage' => $award['percentage'],
                'points' => $award['award_points'],
            ];
        })->toArray();

        $awards[] = [ //add default award
            "ref_award_id" => null,
            "name" => $default_award,
            "min_points" => 0,
            "percentage" => 100,
            "points" => null
        ];

        $collection_initial_points = $groups->level->collection->initial_points;
        $tasks_id = $groups->level->collection->sections->pluck('tasks')->flatten();
        $tasks_answers = DB::select( DB::raw("SELECT task_answers.answer,task_answers.task_id,task_labels.content,competition_tasks_mark.marks,competition_task_difficulty.wrong_marks,competition_task_difficulty.blank_marks FROM `task_answers` LEFT JOIN task_labels ON task_answers.id = task_labels.task_answers_id LEFT JOIN competition_tasks_mark ON task_answers.id = competition_tasks_mark.task_answers_id LEFT JOIN competition_task_difficulty on task_answers.task_id = competition_task_difficulty.task_id WHERE `task_answers`.`task_id` in (" . implode(",",$tasks_id->toArray()) . ") AND task_answers.answer IS NOT NULL AND competition_tasks_mark.level_id = " . $level_id . " AND competition_task_difficulty.level_id = " . $level_id));

        // $competition_organization_id = $groups->level->rounds->competition->competitionOrganization->pluck('id')->toArray();
        // $participants_index = Participants::whereIn('competition_organization_id',$competition_organization_id)->whereIn('country_id',$groups->country_group)->pluck('index_no')->toArray();

        $participants_index = $groups->particitpants_index_no_list->toArray();

        $participants_computed = CompetitionParticipantsResults::whereIn('participant_index',$participants_index)->pluck('participant_index')->toArray();
        $participants_to_compute = array_diff($participants_index,$participants_computed);
        $participants_answers = ParticipantsAnswer::whereIn('participant_index',$participants_to_compute)->orderBy('participant_index')->orderBy('task_id')->get(['id','participant_index','task_id','answer'])->mapToGroups(function ($row) {
            return [$row['task_id'] => $row];
        });

        if($participants_answers->count() == 0 ){
            $groups->status = "computed";
            $groups->save(); //update group status marked
            abort(500,'No student answers to compute');
        }

        collect($tasks_answers)->each(function ($task) use($participants_answers,&$marked,$collection_initial_points){
            collect($participants_answers[$task->task_id]->toArray())->each(function ($answer,$index) use ($task,&$marked,$collection_initial_points) {

                if(!isset($marked[$answer['participant_index']])) { // add index and set initial point in to array if it does not exist
                    $marked[$answer['participant_index']] = $collection_initial_points;
                }

                if($answer['answer'] == $task->answer) {
                    $marked[$answer['participant_index']] += $task->marks;
                }

                if ($answer['answer'] != $task->answer) {
                    $marked[$answer['participant_index']] -= $task->wrong_marks;
                }

                if ($answer['answer'] == null) {
                    $marked[$answer['participant_index']] -= $task->blank_marks;
                }
            });
        });

        /*$marked = [  //test sample
                "094220000013" => 69,
                "094220000014" => 69,
                "094220000015" => 67,
                "094220000016" => 67,
                "094220000017" => 67,
                "094220000018" => 64,
                "094220000019" => 64,
                "094220000020" => 65,
                "094220000021" => 65,
                "094220000022" => 55,
                "094220000023" => 64,
                "094220000024" => 66,
                "094220000025" => 69,
                "094220000026" => 70,
                "094220000027" => 71,
        ];*/


        arsort($marked); // sort array highest to lowest mark

        $insert = collect($awards)->map(function ($award) use(&$marked,&$participantsComputedIndex,$level_id) {
            if(count($marked) > 0) {
                $filterMinMarks = collect($marked)->filter(function($row) use($award) {
                    return $row >= $award['min_points'];
                })->toArray();

                $participantsToExtract =  (($award['percentage'] / 100) * count($filterMinMarks)) > 0 ?  ceil(($award['percentage'] / 100) * count($filterMinMarks)) : 1;
                
                if(count($filterMinMarks) > 0 ) {
                    $awardCutOffMarks = Arr::flatten($filterMinMarks)[$participantsToExtract - 1];
                    $awardedParticipants = collect($filterMinMarks)->takeUntil(function ($item) use($awardCutOffMarks){
                       return $item < $awardCutOffMarks;
                    })->toArray();
                    $marked = array_diff($marked,$awardedParticipants); // remove those participants extracted from the main marked list and proceed to next award.

                    foreach($awardedParticipants as $key => $value) {

                         $awardedParticipants[$key] = [
                             'competition_levels_id' => $level_id,
                             'participant_index' => $key,
                             'ref_award_id' => $award['ref_award_id'] == NULL ? NULL : $award['ref_award_id'],
                             'award_id' => $award['ref_award_id'] == NULL ? NULL : $award['ref_award_id'],
                             'points' => $value,
                        ];

                        $participantsComputedIndex[] = $key;
                    };

                    return $awardedParticipants;
                }
            }
        })->collapse()->toArray();

        $participantsAbsentIndex = array_diff($participants_index,$participantsComputedIndex);

        DB::beginTransaction();

        $groups->status = "computed";
        $groups->save(); //update group status marked

        Participants::whereIn('index_no',$participantsAbsentIndex)->update(['status' => 'absent']);
        Participants::whereIn('index_no',$participantsComputedIndex)->update(['status' => 'result computed']); //update participant status

        foreach($insert as $row) {
            // if("062220002105" == $row['participant_index']) {
            //     dd($row);
            // }
            $results[] = $participantsAnswers = new CompetitionParticipantsResults;
            $participantsAnswers->competition_levels_id = $row['competition_levels_id'];
            $participantsAnswers->participant_index = $row['participant_index'];
            $participantsAnswers->points = $row['points'];
            if($row['ref_award_id'] != null) {
                $participantsAnswers->ref_award_id = $row['ref_award_id'];
                $participantsAnswers->award_id = $row['award_id'];
            }
            $participantsAnswers->save();
        }

        // $result = CompetitionParticipantsResults::insert($insert); //insert computed results
        DB::commit();

        return ['groups_id' => $groups->id, 'groups_country' => $groups->country_group ,'results'=>$results];
    }
}
