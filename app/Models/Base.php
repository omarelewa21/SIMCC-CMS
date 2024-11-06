<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Base extends Model
{
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'created_by',
        'last_modified_by'
    ];

    /**
     * set created by attribute
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function createdBy(): Attribute
    {
        return Attribute::make(
            get: function($value, $attributes){
                if (array_key_exists('created_by_userid', $attributes) && !is_null($attributes['created_by_userid'])){
                    return sprintf(
                        "%s %s",
                        User::whereId($attributes['created_by_userid'])->value('username'),
                        !is_null($attributes['created_at']) ? Carbon::parse($attributes['created_at'])->format("d/m/Y") : '-'
                    );
                }
                return '-';
            }
        );
    }

    /**
     * set last modified by attribute
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function lastModifiedBy(): Attribute
    {
        return Attribute::make(
            get: function($value, $attributes){
                if (array_key_exists('last_modified_userid', $attributes) && !is_null($attributes['last_modified_userid'])){
                    return sprintf(
                        "%s %s",
                        User::whereId($attributes['last_modified_userid'])->value('username'),
                        !is_null($attributes['updated_at']) ?  Carbon::parse($attributes['updated_at'])->format("d/m/Y") : '-'
                    );
                }
                return '-';
            }
        );
    }

    public function tags () {
        return $this->morphToMany(DomainsTags::class, 'taggable');
    }

    public function gradeDifficulty () {
        return $this->morphMany(RecommendedDifficulty::class,'gradeDifficulty');
    }
}
