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
        $participantIds = $this->participants_answers_indices ?? [];

        if (empty($participantIds)) {
            return Participants::where('id', null);
        }
        return Participants::with(['country', 'school', 'competition_organization'])->whereIn('index_no', function ($query) use ($participantIds) {
            $query->select('participant_index')
                ->from('participant_answers')
                ->whereIn('id', $participantIds);
        });
    }
}
