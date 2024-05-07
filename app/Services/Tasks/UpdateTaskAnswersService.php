<?php

namespace App\Services\Tasks;

use App\Http\Requests\Task\UpdateTaskAnswerRequest;
use App\Models\CompetitionTasksMark;
use App\Models\Tasks;
use App\Models\TasksAnswers;

class UpdateTaskAnswersService
{
    private Tasks $task;
    private array $taskAnswerIds = [];

    function __construct(private UpdateTaskAnswerRequest $request)
    {
        $this->task = Tasks::findOrFail($this->request->id);
        $this->updateAnswer();
    }

    public function updateAnswer()
    {
        $taskData = $this->request->only(['answer_type', 'answer_structure', 'answer_layout', 'answer_sorting']);
        if ($this->task->update($taskData)) {
            $this->proceedToAddOrUpdateAnswers();
        } else {
            throw new \Exception('Failed to update task answers');
        }
    }

    private function proceedToAddOrUpdateAnswers()
    {
        $this->request->answer_type == 1
            ? $this->proceedToAddOrUpdateMCQAnswers()
            : $this->proceedToAddOrUpdateInputAnswers();

        $this->updateTaskMark();
        $this->deleteRemovedAnswers();
    }

    private function proceedToAddOrUpdateMCQAnswers()
    {
        foreach($this->request->answers as $key => $answer) {
            is_null($answer['answer_id'])
                ? $this->createNewAnswer($answer, $key)
                : $this->updateExistingAnswer($answer, $key);
        }
    }

    private function proceedToAddOrUpdateInputAnswers()
    {
        $answer = $this->request->answers[0];
        $this->updateExistingAnswer($answer, 0);
    }

    private function createNewAnswer(array $answer, int $key)
    {
        $taskAnswer = TasksAnswers::create([
            'task_id'   => $this->task->id,
            'lang_id'   => env('APP_DEFAULT_LANG', 171),
            'answer'    => $answer['answer'],
            'position'  => $key + 1
        ]);

        $taskAnswer->taskLabels()->create([
            'lang_id'   => env('APP_DEFAULT_LANG', 171),
            'content'   => $answer['label'] ?? '-'
        ]);

        $this->taskAnswerIds[] = $taskAnswer->id;
    }

    private function updateExistingAnswer(array $answer, int $key)
    {
        $taskAnswer = TasksAnswers::find($answer['answer_id']);
        $taskAnswer->answer = $answer['answer'];
        $taskAnswer->position = $key + 1;
        $taskAnswer->save();

        $taskAnswer->taskLabels()->whereId($answer['label_id'])
            ->update([
                'content'   => $answer['label'] ?? '-'
            ]);
        $this->taskAnswerIds[] = $taskAnswer->id;
    }

    private function deleteRemovedAnswers()
    {
        TasksAnswers::where('task_id', $this->task->id)
            ->whereNotIn('id', $this->taskAnswerIds)
            ->get()
            ->each(function($taskAnswer) {
                $taskAnswer->delete();
            });
    }

    private function updateTaskMark()
    {
        $correctAnswersId = $this->getCorrectAnswerFromRequest();
        if(is_null($correctAnswersId)) return;

        CompetitionTasksMark::join('task_answers', 'task_answers.id', '=', 'competition_tasks_mark.task_answers_id')
            ->where('task_answers.task_id', $this->task->id)
            ->update(['task_answers_id' => $correctAnswersId]);
    }

    private function getCorrectAnswerFromRequest()
    {
        return $this->request->answer_type === 1
            ? $this->getCorrectAnswerForMCQ()
            : $this->getCorrectAnswerForInput();
    }

    private function getCorrectAnswerForMCQ(): int
    {
        return TasksAnswers::whereIn('id', $this->taskAnswerIds)
            ->where('answer', "1")
            ->value('id');
    }

    private function getCorrectAnswerForInput(): int
    {
        return TasksAnswers::whereIn('id', $this->taskAnswerIds)
            ->value('id');
    }
}
