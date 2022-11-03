<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Moderation extends Model
{
    use HasFactory;

    protected $table = 'moderation';
    protected $guarded =[];

    public function moderation () {
        return $this->morphTo();
    }

    public function user() {
        return $this->belongsTo(User::class,'moderation_by_userid','id');
    }
}
