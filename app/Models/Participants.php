<?php

namespace App\Models;

use App\Http\Requests\getParticipantListRequest;
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
        "last_modified_userid"
    ];

    /**
     * Scope a query to request params
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  getParticipantListRequest $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterList($query, getParticipantListRequest $request)
    {
        foreach($request->all() as $key=>$value){
            switch($key) {
                case 'search':
                    $query->where('participants.name', 'like', "%$value%")
                        ->orWhere('participants.index_no', $value)
                        ->orWhere('schools.name', 'like', "%$value%")
                        ->orWhere('tuition_centre.name', 'like', "%$value%");
                    break;
                case 'private':
                    $request->private
                    ? $query->whereNotNull("tuition_centre_id")
                    : $query->whereNull("tuition_centre_id");
                    break;
                case 'country_id':
                case 'school_id':
                case 'grade':
                case 'status':
                    $query->where("participants.$key", $value);
                    break;
                case 'organization_id':
                    $query->where('organization.id', $value);
                    break;
                case 'competition_id':
                    $query->where('competition.id', $value);
                    break;
                case 'page':
                case 'limits':
                    break;
                default:
                    $query->where($key, $value);
            }
        }

        switch(auth()->user()->role_id) {
            case 2:
            case 4:
                $ids = CompetitionOrganization::where([
                    'country_id'        => auth()->user()->country_id,
                    'organization_id'   => auth()->user()->organization_id
                ])->pluck('id')->toArray();
                $query->whereIn("competition_organization_id", $ids);
                break;
            case 3:
            case 5:
                $ids = CompetitionOrganization::where([
                    'country_id'        => auth()->user()->country_id,
                    'organization_id'   => auth()->user()->organization_id
                ])->pluck('id')->toArray();
                $query->whereIn("competition_organization_id", $ids)->where("tuition_centre_id" , auth()->user()->school_id)
                    ->orWhere("schools.id" , auth()->user()->school_id);
                break;
        }
    }


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
