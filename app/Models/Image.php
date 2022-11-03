<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $table = 'image';
    protected $guarded = [];
    protected $hidden =['id','image_id'];

    public function image() {
        return $this->morphTo();
    }


}
