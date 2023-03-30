<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    use HasFactory;

    CONST SUPER_ADMIN_ID = 0;
    CONST ADMIN_ID = 1;
    CONST COUNTRY_PARTNER_ID = 2;
    CONST TEACHER_ID = 3;
    CONST COUNTRY_PARTNER_ASSISTANT_ID = 4;
    CONST SCHOOL_MANAGER = 5;

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
