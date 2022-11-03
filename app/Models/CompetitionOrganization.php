<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;

class CompetitionOrganization extends Base
{
    use HasFactory;
    use Filterable;

    protected $table = "competition_organization";

    protected $fillable = [
        'competition_id',
        'country_id',
        "registration_open_date",
        "organization_id",
        "competition_mode",
        "translate",
        "created_by_userid",
        "last_modified_userid",
        "edit_sessions",
        "status"
        ];

    protected $appends =['created_by','last_modified_by','dates','organization_name'];

    protected $hidden = ['created_by_userid','last_modified_userid'];


    /*
    *
    * relations
    *
    * */

    public function competition () {
        return $this->belongsTo(Competition::class,"competition_id","id");
    }

    public function all_competition_dates () {
        return $this->hasMany(CompetitionOrganizationDate::class,"competition_organization_id","id");
    }

    public function competition_date_earliest () {
        return $this->hasOne(CompetitionOrganizationDate::class,"competition_organization_id","id")->orderBy('competition_date');
    }

    public function organization () {
        return $this->belongsTo(Organization::class,"organization_id","id");
    }

    public function participants () {
        return $this->hasMany(Participants::class,"competition_organization_id",'id');
    }

     public function partner () {
        return $this->belongsTo(User::class,"partner_userid","id",'country_id');
    }

    public function reject_reason () {
        return $this->morphMany(RejectReasons::class,'reject');
    }

    public function getDatesAttribute () {
        return $this->all_competition_dates()->pluck('competition_date');
    }

    public function getOrganizationNameAttribute () {
        return $this->organization()->pluck('name')->first();
    }


    /*
     *
     * Local Scope
     *
     * */

/*    public function scopePendingPartner($query) {
        $query->with(['competition:id,name',
            'partner',
            'partner.countryName',
            'partner.organization',
            'competition_dates',
            'reject_reason:reject_id,reason,created_at,created_by_userid',
            'reject_reason.user:id,username',
            'reject_reason.role:roles.name'])
            ->select('id','registration_open_date','competition_id','partner_userid','status')
            ->whereHas('partner', function ($query) {
                return $query->where('status','active');
            })
            ->whereIn('status',['pending']);
    }*/

}
