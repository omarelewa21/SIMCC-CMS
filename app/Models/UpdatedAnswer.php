<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpdatedAnswer extends Model
{
    use HasFactory;

    protected $table = 'updated_answers';

    protected $fillable = [
        'level_id', 'task_id', 'answer_id', 'participant_index',
        'old_answer', 'new_answer', 'reason', 'updated_by'
    ];

    public function level()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id');
    }

    public function task()
    {
        return $this->belongsTo(Tasks::class, 'task_id');
    }

    public function answer()
    {
        return $this->belongsTo(ParticipantsAnswer::class, 'answer_id');
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
