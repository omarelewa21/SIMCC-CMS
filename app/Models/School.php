<?php

namespace App\Models;

use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;
    use Filterable;

    private static $whiteListFilter = [
        'name',
        'status',
        'country_id',
        'private'
    ];

    protected $table = 'schools';
    protected $guarded = [];

    public function country()
    {
        return $this->belongsTo(Countries::class,"country_id","id");
    }

    public function teachers ()
    {
        return $this->hasMany(User::class,"school_id","id")->where('role_id',3);
    }

    public function organization ()
    {
        return $this->belongsTo(Organization::class, "organization_id","id");
    }

    public function created_by ()
    {
        return $this->belongsTo(User::class,"created_by_userid","id");
    }

    public function modified_by ()
    {
        return $this->belongsTo(User::class,"last_modified_userid","id");
    }

    public function approved_by ()
    {
        return $this->belongsTo(User::class,"approved_by_userid","id");
    }

    public function reject_reason () {
        return $this->morphMany(RejectReasons::class,'reject');
    }

}
