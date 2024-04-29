<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;

class Competition extends Base
{
    use HasFactory, Filterable;

    const GOLBAL = 1;       // format column
    const LOCAL = 0;        // format column

    protected $table = "competition";

    protected $with = ['rounds'];

    protected $hidden = ['created_by_userid', 'last_modified_userid'];

    protected $appends = [
        'created_by',
        'last_modified_by',
        'award_type_name',
        'compute_status'
        // 'generate_report_btn'
    ];

    private static $whiteListFilter = [
        'id',
        'status',
        'format',
        'name'
    ];

    protected $fillable = [
        "name",
        'global_registration_date',
        'global_registration_end_date',
        "competition_start_date",
        "competition_end_date",
        "competition_mode",
        "parent_competition_id",
        "allowed_grades",
        "alias",
        "format",
        "status",
        "created_by_userid",
        "difficulty_group_id",
        "award_type",
        "min_points",
        "default_award_name",
        "is_verified"
    ];

    public static function scopeApplyFilter($query, $request)
    {
        return $query
            ->when($request->filled("tag_id"), function($query) use($request){
                $tags = explode(',', $request->tag_id);
                $query->whereHas('tags', fn($query) => $query->whereIn('domains_tags.id', $tags));
            })
            ->when($request->filled("format"), fn() => $query->where('format', $request->format))
            ->when($request->filled("status"), fn() => $query->where('status', $request->status));
    }

    public static function booted()
    {
        parent::booted();
        static::deleting(function ($competition) {
            $competition->competitionOrganization()->delete();
            $competition->groups()->delete();
        });
    }

    protected function competitionStartDate(): Attribute
    {
        return Attribute::make(
            get: function (string $value) {
                if ($value && !empty($value)) return date("Y/m/d", strtotime($value));
                return $value;
            }
        );
    }

    protected function competitionEndDate(): Attribute
    {
        return Attribute::make(
            get: function (string $value) {
                if ($value && !empty($value)) return date("Y/m/d", strtotime($value));
                return $value;
            }
        );
    }

    public function competitionOrganization()
    {
        return $this->hasMany(CompetitionOrganization::class, 'competition_id', 'id');
    }

    public function taskDifficultyGroup()
    {
        return $this->hasOne(TaskDifficultyGroup::class, 'id', 'difficulty_group_id');
    }

    public function taskDifficulty()
    {
        return $this->hasManyThrough(TaskDifficulty::class, TaskDifficultyGroup::class, 'id', 'difficulty_groups_id', 'difficulty_group_id', 'id');
    }

    public function rounds()
    {
        return $this->hasMany(CompetitionRounds::class, "competition_id");
    }

    public function levels()
    {
        return $this->hasManyThrough(CompetitionLevels::class, CompetitionRounds::class, 'competition_id', 'round_id', 'id', 'id');
    }

    public function groups()
    {
        return $this->hasMany(CompetitionMarkingGroup::class, 'competition_id');
    }

    public function overallAwardsGroups()
    {
        return $this->hasMany(CompetitionOverallAwardsGroups::class, 'competition_id');
    }

    public function participants()
    {
        return $this->hasManyThrough(Participants::class, CompetitionOrganization::class, 'competition_id', 'competition_organization_id', 'id', 'id');
    }

    public function integrityCases()
    {
        return $this->hasMany(CheatingParticipants::class, 'competition_id', 'id');
    }

    public function integrityCheckCountries()
    {
        return $this->hasMany(IntegrityCheckCompetitionCountries::class, 'competition_id', 'id');
    }

    public function setAllowedGradesAttribute($value)
    {
        $this->attributes['allowed_grades'] = json_encode($value);
    }

    public function getAllowedGradesAttribute($value)
    {
        return json_decode($value);
    }

    public function getComputeStatusAttribute()
    {
        return 'Finished';
    }

    public function getGenerateReportBtnAttribute()
    {
        $levels = $this->rounds->pluck('levels')->flatten()->pluck('id');
        $found = CompetitionMarkingGroup::whereIn('competition_level_id', $levels)->count() > 0 ?  1 : 0;

        return $found;
    }

    public function getAwardTypeNameAttribute()
    {
        switch ($this->award_type) {
            case 0:
                return 'percentage';
            case 1:
                return 'position';
        }
    }

    public function getActiveParticipantsByCountry($country_id)
    {
        return $this->participants()->where('country_id', $country_id)->get();
    }

    public function totalTasksCount()
    {
        $collectionIds =
            $this->rounds()->join('competition_levels as cl', 'cl.round_id', 'competition_rounds.id')
            ->join('collection', 'collection.id', 'cl.collection_id')
            ->select('collection.id as id')->distinct()
            ->pluck('id')->toArray();

        $sections = CollectionSections::distinct()->whereIn('collection_id', $collectionIds)->get();
        $count = 0;
        foreach ($sections as $section) {
            if ($section->count_tasks) {
                $count += $section->count_tasks;
            }
        }
        return $count;
    }

    public function createGlobalMarkingGroup()
    {
        $countries = $this->competitionOrganization()
            ->pluck('competition_organization.country_id')->unique()->toArray();
        $markingGroup = CompetitionMarkingGroup::firstOrCreate(
            ['competition_id' => $this->id],
            ['name' => "Global Group", 'created_by_userid' => auth()->id()]
        );
        foreach ($countries as $country_id) {
            DB::table('competition_marking_group_country')->updateOrInsert(
                ['marking_group_id' => $markingGroup->id, 'country_id' => $country_id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function isComputed()
    {
        return $this->levels()->where('computing_status', '<>', CompetitionLevels::STATUS_FINISHED)->doesntExist();
    }

    public function isVerified()
    {
        $all_collections_verified = true;
        foreach ($this->rounds as $round) {
            foreach ($round->levels as $level) {
                $collection = $level->collection;
                if ($collection->status === 'deleted') {
                    continue; // Skip deleted collections
                }
                if ($collection->status !== 'verified' || !$this->checkDifficultyIsVerified($round->id, $level->id, $this->id)) {
                    $all_collections_verified = false;
                    break 2; // Break out of both loops if one collection is not verified
                }
            }
        }
        return $all_collections_verified;
    }


    public function checkDifficultyIsVerified($roundId, $levelId, $competitionId)
    {
        $taskDifficulty = TaskDifficultyVerification::where('competition_id', $competitionId)->where('round_id', $roundId)->where('level_id', $levelId)->first();
        if ($taskDifficulty) {
            return true;
        }
        return false;
    }


}
