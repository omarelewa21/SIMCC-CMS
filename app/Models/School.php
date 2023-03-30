<?php

namespace App\Models;

use App\Traits\ApprovedByTrait;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Base
{
    use HasFactory, Filterable, ApprovedByTrait;

    public static function booted()
    {
        parent::booted();

        static::creating(function($school) {
            $school->created_by_userid = auth()->id();
        });
        static::saving(function($school) {
            $school->last_modified_userid = auth()->id();
            $school->updated_at = now()->toDateTimeString();
        });
    }

    private static $whiteListFilter = [
        'name',
        'status',
        'country_id',
        'private'
    ];

    protected $table = 'schools';
    protected $guarded = [];

    public static function booted()
    {
        parent::booted();

        static::creating(function($school) {
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
