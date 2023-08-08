<?php

namespace App\Services\Collection;

use App\Models\Collections;
use App\Models\CollectionSections;
use App\Services\Tasks\DuplicateTaskService;
use Illuminate\Http\Request;

class DuplicateCollectionService
{
    public Request $request;
    public Collections $collection;

    public function __construct(Request $request, Collections $collection)
    {
        $this->request = $request;
        $this->collection = $collection->load(['tags', 'sections', 'gradeDifficulty']);
    }

    public function duplicate()
    {
        $newCollection = $this->collection->replicate();
        $counts = Collections::where('identifier', 'like', "$newCollection->identifier-%")->count();
        $newCollection->identifier = "$newCollection->identifier-" . ($counts + 1);
        $newCollection->status = auth()->user()->hasRole(['super admin', 'admin']) ? Collections::STATUS_ACTIVE : Collections::STATUS_PENDING_MODERATION;
        $newCollection->save();
        $this->syncRelations($newCollection);
    }

    private function syncRelations(Collections $newCollection)
    {
        $this->duplicateCollectionTags($newCollection);
        $this->duplicateCollectionGradeDifficulty($newCollection);
        $this->duplicateCollectionSections($newCollection);
    }

    private function duplicateCollectionTags(Collections $newCollection)
    {
        if(!empty($this->collection->tags)){
            $newCollection->tags()->attach($this->collection->tags->pluck('id'));
        }
    }

    private function duplicateCollectionGradeDifficulty(Collections $newCollection)
    {
        foreach($this->collection->gradeDifficulty as $gradeDifficulty){
            $newCollection->gradeDifficulty()->create([
                'grade' => $gradeDifficulty->grade,
                'difficulty' => $gradeDifficulty->difficulty,
            ]);
        }
    }

    private function duplicateCollectionSections(Collections $newCollection)
    {
        if($this->shouldCreateNewSetOfTasks()){
            $this->duplicateSectionsWithNewTasks($newCollection);
        } else {
            $this->duplicateSectionsWithSameTaskIds($newCollection);
        }
    }

    private function shouldCreateNewSetOfTasks()
    {
        // TODO: Should implement a way to duplicate tasks with new ids
        return false;
        return $this->collection->status === Collections::STATUS_VERIFIED ||
            (
                $this->request->has('duplicate_tasks')
                && $this->request->duplicate_tasks == 1
            );
    }

    private function duplicateSectionsWithNewTasks(Collections $newCollectionm)
    {
        return; // TODO: Should implement a way to duplicate tasks with new ids
        foreach($this->collection->sections as $section){
            foreach($section->section_task as $task){
                $newTask = (new DuplicateTaskService($task))->duplicate();

            }
        }
    }

    private function duplicateSectionsWithSameTaskIds(Collections $newCollectionm)
    {
        foreach($this->collection->sections as $section){
            $newSection = $section->replicate();
            $newSection->collection_id = $newCollectionm->id;
            $newSection->save();
        }
    }
}
