<?php

namespace App\Http\Services;

use App\Http\Controllers\Controller;
use App\Http\Requests\tasks\TasksListRequest;
use App\Models\Tasks;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TasksService
{
    /**
     * get tasks list collection
     * 
     * @param \App\Http\Requests\tasks\TasksListRequest $request
     * @return \Illuminate\Support\Collection
     */
    public static function getTaskListCollection(TasksListRequest $request)
    {
        $eagerLoad = [
            'tags:id,is_tag,domain_id,name',
            'Moderation:moderation_id,moderation_date,moderation_by_userid',
            'Moderation.user:id,username',
            'gradeDifficulty:gradeDifficulty_id,grade,difficulty',
        ];

        if(auth()->user()->hasRole(['super admin', 'admin'])){
            $eagerLoad = array_merge($eagerLoad, [
                'taskAnswers:id,task_id,answer,position',
                'taskAnswers.taskLabels:task_answers_id,lang_id,content'
            ]);
        }

        $hide = ['created_by_userid', 'last_modified_userid'];
        if(!$request->has($request->id) && !$request->has('identifier')){
            $hide[] = 'image';
        }

        $taskModel = Tasks::with($eagerLoad)
            ->AcceptRequest(['id', 'status', 'identifier'])
            ->where('tasks.status', '!=', 'deleted')
            ->when(auth()->user()->hasRole(['super admin', 'admin']), 
                fn($query)=> $query->where('created_by_userid', '!=', auth()->id())
            );

        return self::applyFilter($taskModel, $request)->get()->makeHidden($hide);
    }

    /**
     * Filter the tasks according to the request parameters
     * 
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     * @param \App\Http\Requests\tasks\TasksListRequest $request
     * 
     * @return \Illuminate\Contracts\Database\Eloquent\Builder $query
     */
    public static function applyFilter(Builder $query, TasksListRequest $request)
    {
        if($request->filled("domains") || $request->filled("tags")){
            $query->when($request->filled("domains"), function($query)use($request){
                $query->whereHas('tags', function($query)use($request){
                    $query->whereIn('domains_tags.id', explode(',', $request->domains));
                });
            })->when($request->filled("tags"), function($query)use($request){
                $query->whereHas('tags', function($query)use($request){
                    $query->whereIn('domains_tags.id', explode(',', $request->tags));
                });
            });
        }
        return $query->filter();
    }

    /**
     * get Task filter options
     * 
     * @param \Illuminate\Support\Collection $taskCollection
     * @param \App\Http\Requests\tasks\TasksListRequest $request
     * @return array
     */
    public static function getTaskListFilterOptions(Collection $taskCollection, TasksListRequest $request)
    {
        $taskTitle = $taskCollection->map(function ($item) {
            foreach($item['languages'] as $row) {
                $noTitle = ["id" => $row['id'], "name" => $row['name']];
                $withTitle = array_merge($noTitle, ["title" => $row['task_title']]);
                return ["no_title" => $noTitle, "with_title" => $withTitle];
            }
        });

        $taskCollection = $taskCollection->map(function ($item, $key) use ($taskTitle){
            $item['title'] = $taskTitle[$key]['with_title']['title'];
            return $item;
        });

        if($request->has('lang_id') || $request->has('tag_id') ) {
            $taskCollection = Controller::filterCollectionList($taskCollection, [
                    "1,languages" => $request->lang_id ?? false,
                    "1,tags" =>  $request->tag_id ?? false
                ]
            );
        }

        $instance = new self();
        return [
            'status'    => $instance->getAvailableStatussesInTaskCollection($taskCollection),
            'lang'      => $instance->getAvailableLanguagesInTaskCollection($taskTitle),
            'domains'   => $instance->getAvailableDomainTypesInTaskCollection($taskCollection),
            'tags'      => $instance->getAvailableTagTypesInTaskCollection($taskCollection)
        ];
    }

    private function getAvailableStatussesInTaskCollection(Collection $taskCollection): Collection
    {
        return $taskCollection->map(fn($item)=> $item['status'])->unique()->values();
    }

    private function getAvailableLanguagesInTaskCollection(Collection $taskTitle): Collection
    {
        return 
            $taskTitle->map(function ($item, $key) {
                if(isset($item['no_title'])) {
                    return $item['no_title'];
                }
            })->unique()->values();
    }

    private function getAvailableDomainTypesInTaskCollection(Collection $taskCollection): Collection
    {
        return 
            $taskCollection->map(function ($item) {
                $temp = [];
                foreach($item->toArray()['tags'] as $row) {
                    if($row['domain_id'] && !$row['is_tag']) {
                        $temp[] = ["id" => $row['id'], "name" => $row['name']];
                    }
                }
                return $temp;
            })->filter()->collapse()->unique()->values();
    }

    private function getAvailableTagTypesInTaskCollection(Collection $taskCollection): Collection
    {
        return
            $taskCollection->map(function ($item) {
                $temp = [];
                foreach($item->toArray()['tags'] as $row) {
                    if($row['is_tag']) {
                        $temp[] = ["id" => $row['id'], "name" => $row['name']];
                    }
                }
                return $temp;
            })->filter()->collapse()->unique()->values();
    }
}