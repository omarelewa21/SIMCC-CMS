<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CheatingStatus extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'competition_cheat_compute_status';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

     /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $appends = ['original_countries'];

    /**
     * Get the competition that owns the cheating status.
     */
    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * Scope a query to request params
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array|null $countries
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByCountries($query, array|null $countries=null)
    {
        if ($countries && !empty($countries)) {
            $query->whereJsonContains('countries', $countries);
        } else {
            $query->whereNull('countries');
        }

        return $query;
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

    protected function originalCountries(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['countries'] ? json_decode($attributes['countries'], true) : [],
        );
    }
}
