<?php

namespace App\Models;

use App\Models\Scopes\StatusScope;
use App\Traits\Filter;
use App\Traits\Search;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([new StatusScope('deleted')])]
class DomainsTags extends Base
{
    use HasFactory, Filterable, Filter, Search, SoftDeletes;

    protected $table = "domains_tags";
    protected $appends =['topic_domain', 'created_by', 'last_modified_by'];
    protected $hidden = ['pivot'];
    protected $searchable = ['name'];
    public $filterable = [
        'id'            => 'id',
        'domains'       => 'domain_id',
        'status'        => 'status',
        'tags'          => 'id',
        'domainswithtopics' => 'id',
    ];

    private static $whiteListFilter = [
        'name',
        'status',
        "domain_id",
    ];

    protected $fillable = [
        "domain_id",
        "name",
        "is_tag",
        "created_by_userid",
        "modified_by_userid",
        "status",
        "deleted_at"
    ];


    public static function boot() {
        parent::boot();

        static::saving(function (DomainsTags $domainTag) {
            $domainTag->last_modified_userid = auth()->id();
            $domainTag->status = auth()->user()->hasRole(['super admin', 'admin']) ? 'active' : 'pending';
            $domainTag->deleted_at = null;
        });

        static::creating(function (DomainsTags $domainTag) {
            $domainTag->created_by_userid = auth()->id();
        });

        static::deleting(function (DomainsTags $domainTag) {
            if(is_null($domainTag->domain_id) && $domainTag->is_tag == 0){
                $domainTag->topics()->update([
                    'status'                => 'deleted',
                    'last_modified_userid'  => auth()->id()
                ]);
                $domainTag->topics()->delete();
            }
            DomainsTags::whereId($domainTag->id)->update([
                'status'                => 'deleted',
                'last_modified_userid'  => auth()->id()
            ]);
        });
    }

    public function created_by ()
    {
        return $this->belongsTo(User::class,"created_by_userid","id");
    }

    public function modified_by ()
    {
        return $this->belongsTo(User::class,"last_modified_userid","id");
    }

    public function topics ()
    {
        return $this->hasMany(DomainsTags::class,"domain_id","id");
    }

    public function domain ()
    {
        return $this->belongsTo(DomainsTags::class,"domain_id","id");
    }

    public function Tasks () {
        return $this->morphedByMany(Tasks::class,'taggable');
    }

    public function Collection () {
        return $this->morphedByMany(Collections::class,'taggable');
    }

    public function getTopicDomainAttribute () {
        $query = $this->select('name')->where('id',$this->domain_id)->first();
        if(isset($query->name)){
            return $query->name;
        }
        return null;
    }
}
