<?php

namespace App\Http\Services;

use App\Http\Requests\collection\CollectionListRequest;
use App\Models\Collections;
use App\Models\CompetitionLevels;
use App\Models\Tasks;
use Illuminate\Support\Collection;

class CollectionsService
{
    /**
     * get collection list collection
     * 
     * @param \App\Http\Requests\collection\CollectionListRequest $request
     * @return \Illuminate\Support\Collection
     */
    public static function getCollectionListCollection(CollectionListRequest $request)
    {
        $query = Collections::with([
            'rejectReasons:id,reject_id,reason,created_at,created_by_userid',
            'tags:id,name',
            'gradeDifficulty',
            'sections',
        ])
        ->AcceptRequest(['status', 'id', 'name', 'identifier'])
        ->filter();

        if($request->has('currentPage') && $request->currentPage === 'moderation'){
            $query->whereIn('status', ['Pending Moderation', 'Rejected']);
        } else {
            $query->whereIn('status', ['Active', 'Pending Moderation']);
        }

        return
            $query->get()
            ->map(function ($item){
                $item->sections->map(function ($section) {
                    foreach($section->tasks as $group) {
                        $tasks = Tasks::with('taskAnswers')->whereIn('id',$group['task_id'])->get()->map(function ($task) {
                            return ['id' => $task->id,'task_title' => $task->languages->first()->task_title,'identifier' => $task->identifier] ;
                        });

                        $groups[] = $tasks;
                    }

                    $section->tasks = $groups;
                    return $section;
                });
                return collect($item)->except(['updated_at','created_at','reject_reason','last_modified_userid','created_by_userid']);
        });
    }

    /**
     * get Task filter options
     * 
     * @param \Illuminate\Support\Collection $collections
     * @return array
     */
    public static function getCollectionListFilterOptions(Collection $collections)
    {
        $instance = new self();
        return [
            'status'    => $instance->getAvailableCollectionStatusses($collections),
            'lang'      => $instance->getAvailableCollectionCompetitions($collections),
            'tags'      => $instance->getAvailableCollectionTagTypes($collections)
        ];
    }

    private function getAvailableCollectionStatusses(Collection $collections): Collection
    {
        return $collections->map(fn($item)=> $item['status'])->unique()->values();
    }

    private function getAvailableCollectionCompetitions(Collection $collections): Collection
    {
        return 
            $collections->map(function ($item) {
                $competitions = $item->get('competitions');
                return collect($competitions)->map(function ($competition) {
                    return ["id" => $competition['id'], "name" => $competition['competition']];
                });
            })->filter()->collapse()->unique()->values();
    }

    private function getAvailableCollectionTagTypes(Collection $collections): Collection
    {
        return 
            $collections->map(function ($item) {
                $temp = [];
                foreach($item->toArray()['tags'] as $row) {
                    $temp[] = ["id" => $row['id'], "name" => $row['name']];
                }
                return $temp;
            })->filter()->collapse()->unique()->values();
    }

    public static function CheckUploadedAnswersCount($collectionId)
    {
        $uploadAnswersCount = CompetitionLevels::with(['participantsAnswersUploaded'])
            ->where('collection_id',$collectionId)->get()
            ->pluck('participantsAnswersUploaded')->flatten()
            ->count();
        $uploadAnswersCount == 0 ?:  abort(403, 'Unauthorized action, Answers have been uploaded to collection');
    }
}