<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionTaskDifficulty extends Model
{
    use HasFactory;

    protected $table = 'competition_task_difficulty';
    protected $guarded = [];

    public $timestamps = false;
}
