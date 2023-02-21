<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;

class DomainsTags extends Model
{
    use HasFactory, Filterable, SoftDeletes;

    protected $table = "domains_tags";
    protected $appends =['topic_domain'];
    protected $hidden = ['pivot'];

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
