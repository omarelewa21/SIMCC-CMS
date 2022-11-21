<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CollectionSections extends Model
{
    protected $table = 'collection_sections';
    protected $guarded = '';
    protected $fillable = [
        'options' => 'enabled'
    ];

    protected $appends=['section_task', 'count_tasks'];

    protected $casts = [
        'tasks' => 'json',
    ];

    public $timestamps = false;

    use HasFactory;

    public function groups () {
        return $this->hasMany(CollectionGroups::class,'section_id');
    }

    public function getSectionTaskAttribute ()
    {
        return Tasks::whereIn('id', Arr::flatten($this->tasks))->get();
    }

    public function getCountTasksAttribute ()
    {
        return Tasks::whereIn('id', Arr::flatten($this->tasks))->count();
    }
}
