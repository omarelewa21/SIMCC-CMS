<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TasksLabels extends Model
{
    protected $table = 'task_labels';
    protected $guarded = [];
    protected $hidden = ['task_answers_id'];

    public $timestamps = false;

    use HasFactory;
}
