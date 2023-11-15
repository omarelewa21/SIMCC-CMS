<?php

namespace App\Models;

use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;
    use Filterable;

    const DEFAULT_TUITION_CENTRE_ID = 2;

    private static $whiteListFilter = [
        'name',
        'name_in_certificate',
        'status',
        'country_id',
        'private'
    ];

    protected $table = 'schools';
    protected $guarded = [];
    protected $appends = ['user_can_approve_and_reject'];

    public static function booted()
    {
        parent::booted();

        static::creating(function($school) {
            if($school->is_system_school) return;

            $school->created_by_userid = Auth()->id();
            if(Auth()->user()->hasRole(['Country Partner', 'Country Partner Assistant'])) {
                $school->status = 'pending';
                $school->country_id = Auth()->user()->country_id;
            } else {
                $school->approved_by_userid = Auth()->id();
                $school->status = 'active';
            }
        });

        static::updating(function($school) {
            $school->last_modified_userid = Auth()->id();
        });
    }

    protected function userCanApproveAndReject(): Attribute
    {
        return Attribute::make(function ($value, $attributes) {
            if($attributes['status'] !== 'pending') return false;
            if(auth()->user()->hasRole(['Country Partner', 'Country Partner Assistant'])) {
                return auth()->id() !== $attributes['created_by_userid'] 
                    && auth()->user()->organization_id === User::whereId($attributes['created_by_userid'])->value('organization_id');
            }
            return true;
        });
    }

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

    public function participants()
    {
        return $this->hasMany(Participants::class);
    }
}
