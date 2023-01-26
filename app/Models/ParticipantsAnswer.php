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
    public function getAnswerMark()
    {
        $taskAnswer = $this->getAnswer();
        if(is_null($taskAnswer)){
            return 0;
        }
        
        if(CompetitionTasksMark::where('task_answers_id', $taskAnswer->id)->exists()){
            return CompetitionTasksMark::where('task_answers_id', $taskAnswer->id)->value('marks');
        }

        $minMarks = CompetitionTasksMark::whereIn('task_answers_id', $this->task->taskAnswers()->pluck('task_answers.id'))->value('min_marks');
        return $minMarks ? $minMarks : 0;
    }

    public function getIsCorrectAnswerAttribute(): bool
    {
        if(is_null($this->is_correct)){
            $taskAnswer = $this->getAnswer();
            if(is_null($taskAnswer)){
                return false;
            }
            if(CompetitionTasksMark::where('task_answers_id', $taskAnswer->id)->exists()){
                return true;
            }
            return false;
        }
        return $this->is_correct;
    }
}
