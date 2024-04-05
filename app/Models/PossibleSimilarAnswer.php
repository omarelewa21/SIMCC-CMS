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
        'answer_id',
        'answer_key',
        'possible_key',
        'approved_by',
        'approved_at',
        'status',
    ];

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
}
