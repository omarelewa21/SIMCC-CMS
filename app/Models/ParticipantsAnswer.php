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

    public function task(){
        return $this->belongsTo(Tasks::class, 'task_id', 'id');
    }

    public function isCorrectAnswer()
    {
        return CompetitionTasksMark::where('task_answers_id',
            $this->task->taskAnswers()->where('task_answers.answer', $this->answer)->value('id')
        )->exists();
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class, 'participant_index', 'index_no');
    }

    public function getAnswerMark(){
        if($this->isCorrectAnswer()){
            return CompetitionTasksMark::where('task_answers_id',
                $this->task->taskAnswers()->where('task_answers.answer', $this->answer)->value('id')
            )->value('marks');
        }

        $min_marks = CompetitionTasksMark::whereIn('task_answers_id', $this->task->taskAnswers()->pluck('task_answers.id'))->value('min_marks');
        return $min_marks ? $min_marks : 0;
    }
}
