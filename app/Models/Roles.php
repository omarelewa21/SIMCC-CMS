<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    use HasFactory;

    protected $table = 'roles';
    protected $fillable = [
        'parent_id',
        "name",
        "status",
        "created_by_userid",
        "approved_by_userid",
        "deleted_by_userid",
    ];

    protected $hidden = [
        'deleted_by_userid',
        'status',
        'laravel_through_key'
    ];

    public function users () {
        return $this->belongsTo(User::class,'id','role_id');
    }
}
