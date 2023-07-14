<?php

namespace App\Services\Tasks;

use App\Models\Tasks;
use App\Models\TasksLabels;
use App\Models\User;
use Illuminate\Support\Arr;

class CreateTaskService
{
    public function create(array $data)
    {
        $tasks = User::find(auth()->id())->tasks()->createMany($data);
        foreach($tasks as $index => $task)
        {
            $this->addImage($task, $data[$index]);
            $this->addTags($task, $data[$index]);
            $this->addRecommendedDifficulty($task, $data[$index]);
            $this->addTaskContent($task, $data[$index]);
            $this->addTaskAnswers($task, $data[$index]);
        }
    }

    private function addImage(Tasks $task, array $data)
    {
        if (Arr::has($data, 'image')) {
            $task->taskImage()->create([
                'image_string' => $data['image']
            ]);
        }
    }

    private function addTags(Tasks $task, array $data)
    {
        if (Arr::has($data, 'tag_id')) {
            $task->tags()->attach($data['tag_id']);
        }
    }

    private function addRecommendedDifficulty(Tasks $task, array $data)
    {
        if (Arr::has($data, 'recommended_grade')) {
            if (count($data['recommended_grade']) > 0) {
                for ($i = 0; $i < count($data['recommended_grade']); $i++) {
                    $task->gradeDifficulty()->create(
                        [
                            "grade" => $data['recommended_grade'][$i],
                            "difficulty" => $data['recommended_difficulty'][$i],
                        ]);
                }
            }
        }
    }

    private function addTaskContent(Tasks $task, array $data)
    {
        $task->taskContents()->create([
            'language_id' => env('APP_DEFAULT_LANG'),
            'task_title' => $data['title'],
            'content' => $data['content'],
            'status' => auth()->user()->hasRole(['super admin', 'admin']) ? 'active' : 'pending moderation',
            'created_by_userid' => auth()->id()
        ]);
    }

    private function addTaskAnswers(Tasks $task, array $data)
    {
        $answers = collect($data['answers'])->map(function ($answer, $key) use ($task) {
            $temp = array([
                'task_id' => $task->id,
                'lang_id' => env('APP_DEFAULT_LANG'),
                'answer' => $answer,
                'position' => $key + 1,
            ]);
            return $temp;
        })->toArray();

        $labels = $task->taskAnswers()->createMany(Arr::collapse($answers))
            ->pluck('id')
            ->map(function ($answerId, $key) use ($data) {
                $temp = array([
                    'task_answers_id' => $answerId,
                    'lang_id' => env('APP_DEFAULT_LANG'),
                    'content' => $data['labels'][$key] ?: '-',
                ]);
                return $temp;
        });

        TasksLabels::insert(Arr::collapse($labels));
    }
}
