<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarkingLogs extends Model
{
    use HasFactory;

    const COMPUTE_OPTIONS = [
        'award',
        'school_rank',
        'country_rank',
        'global_rank',
        'remark',
    ];

    public $timestamps = false;

    protected $casts = [
        'computed_at' => 'datetime:Y-m-d',
        'logs' => 'array',
    ];

    protected $fillable = [
        'level_id',
        'group_id',
        'computed_by',
        'computed_at',
        'logs',
    ];

    public static function booted()
    {
        parent::booted();
        static::creating(function ($model) {
            $model->computed_by = auth()->id();
            $model->computed_at = now()->toDateString();
            $model->logs = [
                'options' => $model->getRequestComputeOptions(),
                'clear_previous_results' => request()->input('clear_previous_results') ?? false,
            ];
        });
    }

    protected function computedBy(): Attribute
    {
        return Attribute::make(
            get: fn ($userId) => User::whereId($userId)->value('name'),
        );
    }

    public function level()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id');
    }

    public function group()
    {
        return $this->belongsTo(CompetitionMarkingGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    public function getRequestComputeOptions()
    {
        if(request()->has('not_to_compute')) {
            return array_values(
                array_diff(self::COMPUTE_OPTIONS, request()->input('not_to_compute'))
            );
        }
        return self::COMPUTE_OPTIONS;
    }
}
