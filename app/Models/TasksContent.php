<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TasksContent extends Model
{
    use HasFactory;

    protected $table = 'task_contents';
    protected $guarded =[];

    public function created_by () {
        return $this->belongsTo(User::class, 'created_by_userid', 'id');
    }

    public function language () {
        return $this->hasOne(Languages::class, 'id', 'language_id');
    }

    public function Task() {
        return $this->belongsTo(Task::class, 'task_id','id');
    }
}
