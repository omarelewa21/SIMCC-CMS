<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantsAnswer extends Model
{
    use HasFactory;

    protected $table = 'participant_answers';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    const CREATED_AT = 'created_date';

    public function task()
    {
        return $this->belongsTo(Tasks::class, 'task_id', 'id');
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class, 'participant_index', 'index_no');
    }

    public function getAnswer()
    {
        if($this->task->answer_type === 'mcq'){
            return $this->task->taskAnswers()
                            ->join('task_labels', 'task_labels.task_answers_id', 'task_answers.id')
                            ->where('task_labels.content', $this->answer)->first();
        }

        return $this->task->taskAnswers()->where('task_answers.answer', $this->answer)->first();
    }

    /**
     * return answer mark if available or return the minimum marks for this task
     * 
     * @return int
     */
    public function getAnswerMark($level_id)
    {
        $taskAnswer = $this->getAnswer();
        if(is_null($taskAnswer)){
            return 0;
        }
        
        if(CompetitionTasksMark::where('level_id', $level_id)->where('task_answers_id', $taskAnswer->id)->exists()){
            return CompetitionTasksMark::where('level_id', $level_id)->where('task_answers_id', $taskAnswer->id)->value('marks');
        }

        $minMarks = CompetitionTasksMark::where('level_id', $level_id)
            ->whereIn('task_answers_id', $this->task->taskAnswers()->pluck('task_answers.id')->toArray() )
            ->value('min_marks');

        return $minMarks ?? 0;
    }

    public function getIsCorrectAnswer($level_id): bool
    {
        $taskAnswer = $this->getAnswer();
        if(
            !is_null($taskAnswer)
            && CompetitionTasksMark::where('level_id', $level_id)
                ->where('task_answers_id', $taskAnswer->id)->exists()
            )
        {
            $this->is_correct = true;
        }
        else {
            $this->is_correct = false;
        }

        $this->save();
        return $this->is_correct;
    }
}
