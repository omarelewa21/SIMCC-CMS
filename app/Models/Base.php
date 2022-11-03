<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Base extends Model
{
    public function getCreatedByAttribute() {

        if ($this->created_by_userid){
            $username = User::find($this->created_by_userid)->username;

            return $username . ' ' . ($this->created_at ?? '-');
        }

        return '-';
    }

    public function getLastModifiedByAttribute() {
        if (isset($this->last_modified_userid)){
            $username = User::find($this->last_modified_userid)->username;
            return $username . ' ' .$this->updated_at;
        }
        return '-';
    }

    public function tags () {
        return $this->morphToMany(DomainsTags::class, 'taggable');
    }

    public function gradeDifficulty () {
        return $this->morphMany(RecommendedDifficulty::class,'gradeDifficulty');
    }
}
