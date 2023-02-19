<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Base extends Model
{
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
                        !is_null($attributes['created_at']) ? $attributes['created_at'] : '-'
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
                        !is_null($attributes['updated_at']) ? $attributes['updated_at'] : '-'
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
