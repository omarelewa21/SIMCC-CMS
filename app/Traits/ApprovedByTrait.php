<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ApprovedByTrait
{
    /**
     * set created by attribute
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function approvedBy(): Attribute
    {
        return Attribute::make(
            get: function($value, $attributes){
                if (!is_null($attributes['approved_by_userid'])){
                    return User::whereId($attributes['approved_by_userid'])->value('username');
                }
                return '-';
            }
        );
    }
}