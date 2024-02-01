<?php

namespace App\Models;

use App\Models\Scopes\scopeExcludeCheatingParticipants;
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
        static::addGlobalScope(new scopeExcludeCheatingParticipants);
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

    public function getTaskAnswerIdIfParticipantAnswerKeyExists()
    {
        if($this->task->answer_type === 'mcq'){
            $taskAnswers = $this->task->taskAnswers()
                ->join('task_labels', 'task_labels.task_answers_id', 'task_answers.id')
                ->select('task_answers.id', 'task_labels.content as answer')
                ->get();
        } else {
            $taskAnswers = $this->task->taskAnswers()
                ->select('task_answers.id', 'task_answers.answer')
                ->get();
        }

        return $taskAnswers->first(fn ($taskAnswer) =>
            ltrim($taskAnswer->answer, '0') === ltrim($this->answer, '0')
        )?->id;
    }

    public function getAnswerMark()
    {
        $taskAnswerId = $this->getTaskAnswerIdIfParticipantAnswerKeyExists();

        if(!$taskAnswerId) return $this->getWrongOrBlankMarks($this->level_id);

        $competitionTaskMark = CompetitionTasksMark::where(
            ['level_id' => $this->level_id, 'task_answers_id' => $taskAnswerId]
        )->first();

        return $competitionTaskMark ? $competitionTaskMark->marks : $this->getWrongOrBlankMarks($this->level_id);
    }

    private function getWrongOrBlankMarks()
    {
        $taskDiff = CompetitionTaskDifficulty::where('level_id', $this->level_id)
                        ->where('task_id', $this->task_id)
                        ->first();

        if(is_null($this->answer) || empty($this->answer))  // If answer is empty, return blank marks
            return $taskDiff ? -$taskDiff->blank_marks : 0;

        return $taskDiff ? -$taskDiff->wrong_marks : 0;      // If answer is wrong, return wrong marks
    }

    public function getIsCorrectAnswer(): bool
    {
        $isCorrect = $this->checkIfAnswerIsCorrect($this->level_id);

        if($this->is_correct !== $isCorrect){
            $this->is_correct = $isCorrect;
            $this->save();
        }

        return $this->is_correct;
    }

    private function checkIfAnswerIsCorrect(): bool
    {
        $taskAnswerId = $this->getTaskAnswerIdIfParticipantAnswerKeyExists();

        return $taskAnswerId && CompetitionTasksMark::where('level_id', $this->level_id)
            ->where('task_answers_id', $taskAnswerId)->exists();
    }
}
