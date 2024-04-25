<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegritySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'countries',
        'grades',
        'total_cases_count',
        'run_by',
    ];

    protected $casts = [
        'countries' => 'array',
        'grades' => 'array',
    ];

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
}
