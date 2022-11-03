<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskDifficulty extends Base
{
    use HasFactory;

    protected $table = "difficulty";
    protected $guarded = [];

    public $timestamps = false;

    public function difficultyGroup () {
        $this->belongsTo(TaskDifficultyGroup::class,'id', 'difficulty_groups_id');
    }
}
