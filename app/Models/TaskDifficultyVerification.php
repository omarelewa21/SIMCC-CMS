<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskDifficultyVerification extends Model
{
    use HasFactory;
    protected $table = 'task_difficulty_verification';
    protected $fillable = [
        'is_verified',
        'competition_id',
        'round_id',
        'level_id',
        'verified_by_userid'
    ];
    public $timestamps = false;
}
