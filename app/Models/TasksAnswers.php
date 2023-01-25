<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TasksAnswers extends Model
{
    use HasFactory;

    protected $table = 'task_answers';
    protected $guarded = [];
    protected $hidden = ['task_id'];

    public $timestamps = false;

    public static function booted()
    {
        parent::booted();

        static::deleting(function($task_answer) {
            $task_answer->taskLabels()->delete();
        });
    }

    public function task () {
        return $this->belongsTo(Tasks::class,'task_id','id');
    }

    public function taskLabels () {
        return $this->hasMany(TasksLabels::class, 'task_answers_id','id');
    }
}
