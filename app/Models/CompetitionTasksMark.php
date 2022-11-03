<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionTasksMark extends Model
{
    use HasFactory;

    protected $table = 'competition_tasks_mark';
    protected $guarded = [];

    public $timestamps = false;

    public function taskAnswer () {
        return $this->belongsTo(TasksAnswers::class,"id",'task_answers_id');
    }

}
