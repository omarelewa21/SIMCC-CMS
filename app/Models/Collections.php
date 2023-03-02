<?php

namespace App\Models;

use App\Traits\ApprovedByTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collections extends Base
{
    use HasFactory, Filterable, SoftDeletes, ApprovedByTrait;

    private static $whiteListFilter = [
        'name',
        'status',
        'identifier',
        'id'
    ];

    protected $table = 'collection';
    protected $fillable = ['name','identifier','description','time_to_solve','initial_points','created_by_userid','modified_by_userid','approve_by_userid','status'];
    protected $appends = array(
        'created_by',
        'last_modified_by',
        'competitions',
        'Moderators',
        'allow_delete',
        'allow_update_sections',
        'approved_by'
    );

    public static function booted()
    {
        parent::booted();

        static::creating(function(self $collection) {
            $collection->created_by_userid = auth()->id();
            $collection->status = auth()->user()->hasRole(['Admin', 'Super Admin']) ? 'Active' : 'Pending Moderation';
        });

        static::saving(function(self $collection) {
            if($collection->isDirty()){
                $collection->last_modified_userid = auth()->id();
            }
        });

        static::deleted(function(self $collection) {
            self::withTrashed()->whereId($collection->id)->update([
                'status' => 'deleted',
                'last_modified_userid' => auth()->id()
            ]);
        });
    }

    public function taskTags() {
        return $this->morphToMany(DomainsTags::class, 'taggable')->withTrashed();
    }

    public function moderation () {
        return $this->morphMany(Moderation::class, 'moderation')->limit(5);
    }

    public function sections () {
        return $this->hasMany(CollectionSections::class,'collection_id','id');
    }

//    public function answers () {
//        return $this->hasManyThrough(TasksAnswer::class,Tasks::class, 'id','task_id','id','collection');
//    }

    public function levels () {
        return $this->belongsTo(CompetitionLevels::class,'id','collection_id');
    }

    public function rejectReasons()
    {
        return $this->morphMany(RejectReasons::class, 'reject')->latest();
    }

    public function getCompetitionsAttribute () {
        return CompetitionLevels::with(['rounds.competition'])->where('collection_id',$this->id)->get()->map(function($item) {
            return ["id" => $item->rounds->competition->id,"competition" => $item->rounds->competition->name,"status" => $item->rounds->competition->status];
        });
    }

    public function getModeratorsAttribute () {
        collect(Moderation::with(['user.roles:id,name'])->where(['moderation_type' => 'App\Models\Collections' ,'moderation_id' => $this->id])->get())->map(function ($item) use(&$user) {
            $user = array($item->user->name . ' - ' .  $item->user->roles->first()->name);
        });
        return $user;
    }

    public function getAllowDeleteAttribute()
    {
        return $this->allowedToDelete();
    }

    public function getAllowUpdateSectionsAttribute()
    {
        return $this->allowedToUpdateAll();
    }

    public function allowedToUpdateAll(): bool
    {
        if(!is_null($this->levels)){
            return ParticipantsAnswer::where('level_id', $this->levels->id)->doesntExist();
        }
        return true;
    }

    public function allowedToDelete(): bool
    {
        return CompetitionLevels::where('collection_id', $this->id)->doesntExist();
    }
}
