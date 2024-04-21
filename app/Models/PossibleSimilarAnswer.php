<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PossibleSimilarAnswer extends Model
{
    use HasFactory;

    const STATUS_WAITING_INPUT = 'waiting input';
    const STATUS_APPROVED = 'approved';
    const STATUS_DECLINED = 'declined';

    protected $table = 'possible_similar_answers';

    protected $fillable = [
        'task_id',
        'level_id',
        'answer_id',
        'answer_key',
        'possible_key',
        'participants_answers_indices',
        'approved_by',
        'approved_at',
        'status',
    ];

    protected $casts = ['participants_answers_indices' => 'array'];

    public function task()
    {
        return $this->belongsTo(Tasks::class, 'task_id');
    }

    public function answer()
    {
        return $this->belongsTo(TasksAnswers::class, 'answer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function participants()
    {
        $participantsAnswersIndices = $this->participants_answers_indices ?? [];

        if (empty($participantsAnswersIndices)) {
            return collect();
        }

        $allParticipants = collect();

        foreach ($participantsAnswersIndices as $answerIndex) {
            $participants = Participants::with(['country', 'school', 'competition_organization'])
                ->whereIn('index_no', function ($query) use ($answerIndex) {
                    $query->select('participant_index')
                        ->from('participant_answers')
                        ->where('id', $answerIndex);
                })->get()->each(function ($participant) use ($answerIndex) {
                    $participant->participant_answer_id = $answerIndex;
                });

            $allParticipants = $allParticipants->merge($participants);
        }

        return $allParticipants;
    }
}
