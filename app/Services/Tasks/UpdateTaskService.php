<?php

namespace App\Services\Tasks;

use App\Models\Tasks;
use App\Models\TasksAnswers;

class UpdateTaskService
{
    public function updateAnswer(array $data)
    {
        $task = Tasks::findOrFail($data["id"]);
        if ($task->update($data)) {
            $this->proceedToAddOrUpdateAnswers($data['answers'], $task);
        }
    }

    private function proceedToAddOrUpdateAnswers(array $answers, Tasks $task)
    {
        $taskAnswerIds = [];
        foreach($answers as $key=>$answer) {
            $answer['answer_id'] == null
                ? $this->createNewAnswer($answer, $task, $key, $taskAnswerIds)
                : $this->updateExistingAnswer($answer, $task, $key, $taskAnswerIds);

            $this->deleteRemovedAnswers($task, $taskAnswerIds);
        }
    }

    private function createNewAnswer(array $answer, Tasks $task, int $key, array &$taskAnswerIds)
    {
        $taskAnswer = TasksAnswers::create([
            'task_id'   => $task->id,
            'lang_id'   => env('APP_DEFAULT_LANG', 171),
            'answer'    => $answer['answer'],
            'position'  => $key + 1
        ]);

        $taskAnswer->taskLabels()->create([
            'lang_id'   => env('APP_DEFAULT_LANG', 171),
            'content'   => $answer['label'] ?? '-'
        ]);

        $taskAnswerIds[] = $taskAnswer->id;
    }

    private function updateExistingAnswer(array $answer, Tasks $task, int $key, array &$taskAnswerIds)
    {
        $taskAnswer = TasksAnswers::find($answer['answer_id']);
        $taskAnswer->answer = $answer['answer'];
        $taskAnswer->position = $key + 1;
        $taskAnswer->save();

        $taskAnswer->taskLabels()->whereId($answer['label_id'])
            ->update([
                'content'   => $answer['label'] ?? '-'
            ]);
        
        $taskAnswerIds[] = $taskAnswer->id;
    }

    private function deleteRemovedAnswers(Tasks $task, array $taskAnswerIds)
    {
        TasksAnswers::where('task_id', $task->id)
            ->whereNotIn('id', $taskAnswerIds)
            ->get()
            ->each(function($taskAnswer) {
                $taskAnswer->delete();
            });
    }
}
