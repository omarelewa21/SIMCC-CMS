<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CollectionSections extends Model
{
    protected $table = 'collection_sections';
    protected $guarded = '';

    protected $appends=['section_task'];

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
        $task = Arr::flatten($this->tasks);
        return Tasks::whereIn('id',$task)->get();
    }
}
