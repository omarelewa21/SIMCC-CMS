<?php

namespace App\Models;

use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Participants extends Base
{
    use HasFactory;
    use Filterable;

    private static $whiteListFilter = [
        'status',
        'index_no',
        'country_id',
        'grade',
        'competition_id',
        'school_id'
    ];

    protected $table = 'participants';

    protected $fillable = [
        "name",
        "index_no",
        "country_id",
        "school_id",
        "email",
        "grade",
        "class",
        "tuition_centre_id",
        "competition_organization_id",
        'password',
        "status",
        "session",
        "created_by_userid",
        "last_modified_userid"
    ];

    protected $hidden = [
        'password',
//        "created_by_userid",
        "last_modified_userid"
    ];

//    protected $appends = ['created_by','last_modified_by'];

    public function created_by () {
        return $this->belongsTo(User::class,"created_by_userid","id");
    }

    public function modified_by () {
        return $this->belongsTo(User::class,"last_modified_userid","id");
    }

    public function tuition_centre ()
    {
        return $this->belongsTo(School::class,"tuition_centre_id","id");
    }

    public function school ()
    {
       return $this->belongsTo(School::class,"school_id","id");
    }

    public function country ()
    {
        return $this->belongsTo(Countries::class, 'country_id');
    }

    public function competition_organization () {
        return $this->belongsTo(CompetitionOrganization::class,"competition_organization_id","id");
    }

}
