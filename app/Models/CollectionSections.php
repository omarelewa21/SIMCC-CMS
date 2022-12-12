<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class CollectionSections extends Model
{
    protected $table = 'collection_sections';
    protected $fillable = [
       'options->enabled',
       'collection_id',
       'description',
       'tasks',
       'allow_skip',
       'sort_randomly'
    ];

    protected $appends=['section_task', 'count_tasks'];

    protected $casts = [
        'tasks' => AsArrayObject::class,
    ];

    public $timestamps = false;

    use HasFactory;

    public function groups () {
        return $this->hasMany(CollectionGroups::class,'section_id');
    }

    public function getSectionTaskAttribute ()
    {
        if($this->tasks){
            return Tasks::whereIn('id', Arr::flatten($this->tasks))->get();
        }

        return [];
    }

    public function getCountTasksAttribute ()
    {
        if($this->tasks){
            return Tasks::whereIn('id', Arr::flatten($this->tasks))->count();
        }

        return [];
    }
}
