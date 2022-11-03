<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Organization extends Base
{
    use HasFactory,Filterable;

    private static $whiteListFilter = [
        'name',
        'status',
        'country_id',
        'id'
    ];

    protected $table = 'organization';
    public $hidden = ['updated_at','created_at','modified_by_userid','created_by_userid'];
    protected $appends = array(
        'created_by',
        'last_modified_by',
    );
    protected $guarded = [];

    public function users () {
        return $this->hasMany(User::class,'organization_id','id');
    }
}
