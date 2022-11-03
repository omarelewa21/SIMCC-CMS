<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Base;

class RejectReasons extends Base
{
    use HasFactory;

    protected $guarded =[];
    protected $hidden = [
        'created_by_userid',
        'created_at',
    ];
    protected $appends = ['created_by'];

    public function reject() {
        return $this->morphTo();
    }

    public function user() {
        return $this->belongsTo(User::class,'created_by_userid','id');
    }

    public function role() {
        return $this->hasOneThrough(Roles::class, User::class, 'id','id', 'created_by_userid','role_id' );
    }

}
