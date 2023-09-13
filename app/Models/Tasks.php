<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Tasks extends Base
{
    use HasFactory, Filterable;

    const STATUS_VERIFIED = "Verified";
    const STATUS_PENDING_MODERATION = "Pending Moderation";
    const STATUS_ACTIVE = "Active";
    const STATUS_Rejected = "Rejected";
    const STATUS_Deleted = "Deleted";

    protected $Table = "task";
    protected $fillable = [
        "id",
        "identifier",
        "description",
        "solutions",
        "image_label",
        "answer_type",
        "answer_structure",
        "answer_layout",
        "answer_sorting",
        "created_by_userid",
        "status"
    ];
    protected $appends = array(
        'created_by',
        'last_modified_by',
        'languages',
        'image',
        'answer_type_id',
        'answer_structure_id',
        'answer_layout_id',
        'answer_sorting_id',
        'allow_delete',
        'allow_update_answer',
    );
    public $hidden = ['updated_at', 'created_at'];
    private static $whiteListFilter = [
        'id',
        'lang_id',
        'identifier',
        'status',
    ];

    public static function booted()
    {
        parent::booted();

        static::creating(function ($task) {
            $task->created_by_userid = auth()->user()->id;
        });

        static::deleting(function ($task) {
            $task->taskImage()->delete();

            DB::table('taggables')->where([
                ['taggable_type', 'App\Models\Tasks'],
                ['taggable_id', $task->id],
            ])->delete();

            DB::table('recommended_difficulty')->where([
                ['gradeDifficulty_type', 'App\Models\Tasks'],
                ['gradeDifficulty_id', $task->id],
            ])->delete();

            $task->taskContents()->delete();

            foreach ($task->taskAnswers as $task_answer) {
                $task_answer->delete();
            }
        });
    }

    public function taskTags()
    {
        return $this->morphToMany(DomainsTags::class, 'taggable')->withTrashed();
    }

    public function taskAnswers()
    {
        return $this->hasMany(TasksAnswers::class, 'task_id', 'id');
    }

    public function taskImage()
    {
        return $this->morphOne(Image::class, 'image');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_userid', 'id');
    }

    public function moderation()
    {
        return $this->morphMany(Moderation::class, 'moderation')->limit(5);;
    }

    public function taskContents()
    {
        return $this->hasMany(TasksContent::class, 'task_id', 'id');
    }

    public function gradeDifficulty()
    {
        return $this->morphMany(RecommendedDifficulty::class, 'gradeDifficulty');
    }

    public function getAnswerTypeAttribute($value)
    {
        switch ($value) {
            case 1:
                return 'mcq';
                break;
            case 2:
                return "input";
                break;
            case 3:
                return "interactive";
                break;
        }
    }

    public function getAnswerStructureAttribute($value)
    {
        switch ($value) {
            case 1:
                return 'default';
                break;
            case 2:
                return "group";
                break;
            case 3:
                return "sequence";
                break;
            case 4:
                return "open";
                break;
        }
    }

    public function getAnswerLayoutAttribute($value)
    {
        switch ($value) {
            case 1:
                return 'vertical';
                break;
            case 2:
                return "horizontal";
                break;
        }
    }

    public function getAnswerSortingAttribute($value)
    {
        switch ($value) {
            case 1:
                return 'fix';
                break;
            case 2:
                return "random";
                break;
        }
    }

    public function getAnswerTypeIdAttribute()
    {
        return $this->attributes['answer_type'];
    }

    public function getAnswerStructureIdAttribute()
    {
        return $this->attributes['answer_structure'];
    }

    public function getAnswerLayoutIdAttribute()
    {
        return $this->attributes['answer_layout'];
    }

    public function getAnswerSortingIdAttribute()
    {
        return $this->attributes['answer_sorting'];
    }

    public function getLanguagesAttribute()
    {
        return TasksContent::join('all_languages', 'all_languages.id', '=', 'task_contents.language_id')->where('task_id', $this->id)->get(['all_languages.id', 'all_languages.name', 'task_title', 'content', 'status']);
    }

    public function getImageAttribute()
    {
        return Image::where('image_type', 'App\Models\Tasks')
            ->where('image_id', $this->id)
            ->limit(1)
            ->get()
            ->pluck('image_string')
            ->join('');
    }

    public function getAllowDeleteAttribute()
    {
        return !self::checkStatusForDeletion($this->id);
    }

    public function getAllowUpdateAnswerAttribute()
    {
        return $this->allowedToUpdateAll();
    }

    public static function applyFilter($query, Request $request)
    {
        if ($request->filled("domains") || $request->filled("tags")) {
            $query->when($request->filled("domains"), function ($query) use ($request) {
                $query->whereHas('tags', function ($query) use ($request) {
                    $query->whereIn('domains_tags.id', explode(',', $request->domains));
                });
            })->when($request->filled("tags"), function ($query) use ($request) {
                $query->whereHas('tags', function ($query) use ($request) {
                    $query->whereIn('domains_tags.id', explode(',', $request->tags));
                });
            });
        }
        return $query->filter();
    }

    public function allowedToUpdateAll(): bool
    {
        return ParticipantsAnswer::where('task_id', $this->id)->doesntExist();
    }

    public static function checkStatusForDeletion($task_id)
    {
        return CollectionSections::where('tasks', 'LIKE', "%$task_id%")->exists();
    }
}
