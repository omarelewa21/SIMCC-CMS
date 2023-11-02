<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class CollectionSections extends Model
{
    use HasFactory;

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

    public function groups () {
        return $this->hasMany(CollectionGroups::class,'section_id');
    }

    public function getSectionTaskAttribute ()
    {
        if($this->tasks){
            $taskIds = collect($this->tasks)->flatten()->filter(fn($item) => is_numeric($item))->toArray();
            return Tasks::whereIn('id', $taskIds)->get();
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
