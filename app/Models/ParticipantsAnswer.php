<?php

namespace App\Models;

use App\Models\Scopes\DiscardElminatedParticipantsAnswersScope;
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

    protected static function booted(): void
    {
        static::addGlobalScope(new DiscardElminatedParticipantsAnswersScope);
    }

    public function task()
    {
        return $this->belongsTo(Tasks::class, 'task_id', 'id');
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class, 'participant_index', 'index_no');
    }

    public function level()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id', 'id');
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
            return $this->getWrongOrBlankMarks($level_id);
        }

        $competitionTaskMark = CompetitionTasksMark::where(['level_id' => $level_id, 'task_answers_id' => $taskAnswer->id])
            ->first();
        if($competitionTaskMark){                        // If answer is correct, return the mark for correct answer
            return $competitionTaskMark->marks;
        }

        return $this->getWrongOrBlankMarks($level_id);
    }

    public function getWrongOrBlankMarks($level_id)
    {
        $taskDiff = CompetitionTaskDifficulty::where('level_id', $level_id)
                        ->where('task_id', $this->task_id)
                        ->first();

        if(is_null($this->answer) || empty($this->answer))  // If answer is empty, return blank marks
            return $taskDiff ? -$taskDiff->blank_marks : 0;

        return $taskDiff ? -$taskDiff->wrong_marks : 0;      // If answer is wrong, return wrong marks
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
        if($this->isDirty('is_correct')){
            $this->save();
        }
        return $this->is_correct;
    }
}
