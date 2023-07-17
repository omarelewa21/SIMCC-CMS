<?php

namespace App\Services\Tasks;

use App\Models\Tasks;

class DuplicateTaskService
{
    protected Tasks $task;

    public function __construct(Tasks $task)
    {
        $this->task = $task->load(['taskContents', 'taskImage', 'gradeDifficulty', 'taskTags', 'taskAnswers', 'taskAnswers.taskLabels']);
    }

    public function duplicate()
    {
        $newTask = $this->task->replicate();
        $counts = Tasks::where('identifier', 'like', "$newTask->identifier-%")->count();
        $newTask->identifier = "$newTask->identifier-" . ($counts + 1);
        $newTask->save();
        $this->syncRelations($newTask);
    }

    private function syncRelations(Tasks $newTask)
    {
        $this->duplicateTaskImage($newTask);
        $this->duplicateTaskTags($newTask);
        $this->duplicateTaskGradeDifficulty($newTask);
        $this->duplicateTaskContent($newTask);
        $this->duplicateTaskAnswers($newTask);
    }

    private function duplicateTaskImage(Tasks $newTask)
    {
        if($this->task->taskImage){
            $newTask->taskImage()->create([
                'image_string' => $this->task->taskImage->image_string
            ]);
        }
    }

    private function duplicateTaskTags(Tasks $newTask)
    {
        if(!empty($this->task->taskTags)){
            $newTask->tags()->attach($this->task->taskTags->pluck('id'));
        }
    }

    private function duplicateTaskGradeDifficulty(Tasks $newTask)
    {
        foreach($this->task->gradeDifficulty as $gradeDifficulty){
            $newTask->gradeDifficulty()->create([
                'grade' => $gradeDifficulty->grade,
                'difficulty' => $gradeDifficulty->difficulty,
            ]);
        }
    }

    private function duplicateTaskContent(Tasks $newTask)
    {
        foreach($this->task->taskContents as $taskContent){
            $newTask->taskContents()->create([
                'language_id' => $taskContent->language_id,
                'task_title' => $taskContent->task_title,
                'content' => $taskContent->content,
                'status' => $taskContent->status,
                'created_by_userid' => $taskContent->created_by_userid
            ]);
        }
    }
    

    private function duplicateTaskAnswers(Tasks $newTask)
    {
        foreach($this->task->taskAnswers as $taskAnswer){
            $newTaskAnswer = $newTask->taskAnswers()->create([
                'lang_id' => $taskAnswer->lang_id,
                'position' => $taskAnswer->position,
                'answer' => $taskAnswer->answer,
            ]);
            $this->duplicateTaskLabels($newTaskAnswer, $taskAnswer);
        }
    }

    private function duplicateTaskLabels($newTaskAnswer, $taskAnswer)
    {
        foreach($taskAnswer->taskLabels as $taskLabel){
            $newTaskAnswer->taskLabels()->create([
                'content' => $taskLabel->content,
                'lang_id' => $taskLabel->lang_id
            ]);
        }
    }
}
