<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PossibleAnswer extends Model
{
    use HasFactory;

    protected $table = 'possible_answers';

    protected $fillable = [
        'competition_id',
        'level_id',
        'collection_id',
        'section_id',
        'task_id',
        'answer_id',
        'answer_key',
        'possible_keys',
        'approved_by',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'possible_keys' => 'array',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function level()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id');
    }

    public function collection()
    {
        return $this->belongsTo(Collections::class, 'collection_id');
    }

    public function section()
    {
        return $this->belongsTo(CollectionSections::class, 'section_id');
    }

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
