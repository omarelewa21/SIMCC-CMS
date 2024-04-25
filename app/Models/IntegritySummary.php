<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class IntegritySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'competition_id',
        'cheating_percentage',
        'number_of_same_incorrect_answers',
        'countries',
        'grades',
        'total_cases_count',
        'run_by',
    ];

    protected $casts = [
        'created_at'    => 'datetime:Y-m-d H:i',
        'updated_at'    => 'datetime:Y-m-d H:i',
    ];

    protected $appends = ['original_countries'];

    public static function booted()
    {
        parent::booted();
        static::creating(function ($summary) {
            $summary->run_by = auth()->id();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    protected function runBy(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? User::whereId($value)->value('name') : null,
        );
    }

    protected function grades(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? sort(json_decode($value, true)) : null,
            set: fn($value) => $value ? json_encode($value) : null,
        );
    }

    protected function originalCountries(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['countries'] ? json_decode($attributes['countries'], true) : [],
        );
    }

    protected function countries(): Attribute
    {
        return Attribute::make(
            get: fn (string|null $values) =>
                $values
                    ? Countries::whereIn('id', json_decode($values, true))->pluck('display_name')->join(', ')
                    : "All Countries",

            set: fn ($values) => is_array($values) ? '[' . Arr::join($values, ',') . ']' : $values
        );
    }
}
