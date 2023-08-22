<?php

namespace App\Http\Controllers\Api;

use App\Helpers\General\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\AddSectionRequest;
use App\Http\Requests\Collection\CreateCollectionRequest;
use App\Models\Collections;
use App\Models\CollectionSections;
use App\Models\CompetitionLevels;
use App\Models\DomainsTags;
use App\Models\Competition;
use App\Models\Tasks;
use App\Http\Requests\Collection\DeleteCollectionsRequest;
use App\Http\Requests\Collection\DeleteSectionRequest;
use App\Http\Requests\Collection\UpdateCollectionRecommendationsRequest;
use App\Http\Requests\Collection\UpdateCollectionSectionRequest;
use App\Http\Requests\Collection\UpdateCollectionSettingsRequest;
use App\Models\CompetitionRounds;
use App\Models\CompetitionTaskDifficulty;
use App\Models\CompetitionTasksMark;
use App\Models\TaskDifficulty;
use App\Models\TaskDifficultyGroup;
use App\Models\TaskDifficultyVerification;
use App\Models\TasksAnswers;
use App\Rules\CheckMultipleVaildIds;
use App\Services\Collection\CreateCollectionService;
use App\Services\Collection\DuplicateCollectionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public function list(Request $request)
    {

        $vaildated = $request->validate([
            "id" => "integer",
            'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            'status' => 'alpha',
            'competition_id' => new CheckMultipleVaildIds(new Competition()),
            'tag_id' => new CheckMultipleVaildIds(new DomainsTags()),
            'limits' => 'integer',
            'page' => 'integer',
            'search' => 'max:255'
        ]);


        try {

            $limits = $request->limits ? $request->limits : 10; //set default to 10 rows per page
            $searchKey = isset($vaildated['search']) ? $vaildated['search'] : null;
            $eagerload = [
                'reject_reason:reject_id,reason,created_at,created_by_userid',
                'reject_reason.user:id,username',
                'reject_reason.role:roles.name',
                'tags:id,name',
                'gradeDifficulty',
                'sections',
            ];

            $collectionModel = Collections::with($eagerload)
                ->AcceptRequest(['status', 'id', 'name', 'identifier']);

            $collections = $collectionModel
                ->filter()
                ->orderByRaw(sprintf("FIELD(status, '%s', '%s', '%s') ASC", Collections::STATUS_VERIFIED, Collections::STATUS_ACTIVE, Collections::STATUS_PENDING_MODERATION))
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($item) {
                    $section = $item->sections->map(function ($section) {
                        foreach ($section->tasks as $group) {
                            $tasks = Tasks::with('taskAnswers')->whereIn('id', $group['task_id'])->get()->map(function ($task) {
                                return ['id' => $task->id, 'task_title' => $task->languages->first()->task_title, 'identifier' => $task->identifier];
                            });

                            $groups[] = $tasks;
                        }

                        $section->tasks = $groups;
                        return $section;
                    });

                    return collect($item)->except(['updated_at', 'created_at', 'reject_reason', 'last_modified_userid', 'created_by_userid']);
                });

            /**
             * Lists of availabe filters
             */
            $availCollectionsStatus = $collections->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            $availCollectionsCompetition = $collections->map(function ($item) {
                $competitions = $item->get('competitions');

                return collect($competitions)->map(function ($competition) {
                    return ["id" => $competition['id'], "name" => $competition['competition']];
                });
            })->filter()->collapse()->unique()->values();
            $availTagType = $collections->map(function ($item) {
                $temp = [];

                foreach ($item->toArray()['tags'] as $row) {
                    $temp[] = ["id" => $row['id'], "name" => $row['name']];
                }
                return $temp;
            })->filter()->collapse()->unique()->values();

            /**
             * EOL Lists of availabe filters
             */

            if ($request->has('competition_id') || $request->has('tag_id')) {
                /** addition filtering done in collection**/

                $collections = $this->filterCollectionList(
                    $collections,
                    [
                        "1,competitions" => $request->competition_id ?? false, // 0 = non-nested, 1 = nested
                        "1,tags" =>  $request->tag_id ?? false
                    ]
                );
            }


            $availForSearch = array("identifier", "name", "description");
            $collectionsList = CollectionHelper::searchCollection($searchKey, $collections, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availCollectionsStatus, 'competition' => $availCollectionsCompetition, 'tags' => $availTagType], 'collectionList' => $collectionsList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        } catch (\Exception $e) {
            // do task when error
            return response()->json([
                "status"    => 500,
                "message"   => "Retrieve collection unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function create(CreateCollectionRequest $request)
    {
        DB::beginTransaction();
        try {
            (new CreateCollectionService())->create($request->all());
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Collections create was unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => 'Collections created successfully'
        ]);
    }

    public function update_settings(UpdateCollectionSettingsRequest $request)
    {
        DB::beginTransaction();
        try {
            $collection = Collections::findOrFail($request->collection_id);
            $settings = $request->settings;
            if ($collection->allowedToUpdateAll()) {
                $collection->update($settings);
            } else {
                $collection->update([
                    'time_to_solve'     => $settings['time_to_solve'],
                    'initial_points'    => $settings['initial_points'],
                    'description'       => $settings['description']
                ]);
            }
            if (Arr::has($settings, 'tags') && count($settings['tags']) > 0) {
                $collection->tags()->sync($settings['tags']);
            }
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "collection settings update unsuccessfull" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "collection seetings update successful"
        ]);
    }

    public function update_recommendations(UpdateCollectionRecommendationsRequest $request)
    {
        DB::beginTransaction();
        try {
            $collection = Collections::findOrFail($request->collection_id);
            $collection->gradeDifficulty()->delete();
            if (count($request->recommendations) > 0) {
                collect($request->recommendations)->map(function ($item) use ($collection) {
                    $collection->gradeDifficulty()->create(
                        [
                            "grade"         => $item['grade'],
                            "difficulty"    => $item['difficulty']
                        ]
                    );
                });
            }
        } catch (\Exception $e) {
            return response()->json([
                "status"  => 500,
                "message" => "collection recommendation update successful" . $e->getMessage(),
                "error"   => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "collection recommendation update successful"
        ]);
    }

    public function add_sections(AddSectionRequest $request)
    {
        $validated = $request->all();

        try {
            $collection_id = Arr::pull($validated, 'collection_id');
            $tasks =  json_encode(Arr::pull($validated, 'groups'), JSON_UNESCAPED_SLASHES);

            DB::beginTransaction();

            CollectionSections::insert(
                [
                    "collection_id" => $collection_id,
                    "description" => $validated['description'],
                    "tasks" => $tasks,
                    "allow_skip" =>  $validated['allow_skip'],
                    "sort_randomly" => $validated['sort_randomly']
                ]
            );

            $section = CollectionSections::orderBy('id', 'DESC')->first();
            DB::commit();

            CollectionSections::inset([
                'collection_id'     => $collection_id,
                'description'       => $validated['description'],
                'tasks'             => $tasks,
                'allow_skip'        => $validated['allow_skip'],
                'sort_randomly'     => $validated['sort_randomly']
            ]);

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "collection section update successful",
                "data" => $section
            ]);
        } catch (\Exception $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "collection section update unsuccessful " . $e
            ], 500);
        }
    }

    public function update_sections(UpdateCollectionSectionRequest $request)
    {
        DB::beginTransaction();

        try {
            $section = $request->section;
            $section['tasks'] = Arr::pull($section, 'groups');
            $this->CheckUploadedAnswersCount($request->collection_id);

            if ($request->has('section_id')) {
                $results = CollectionSections::findOrFail($request->section_id);
                $results->update($section);
            } else {
                $section['collection_id'] = $request->collection_id;
                $results = CollectionSections::create($section);
            }
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "collection section update unsuccessful "  . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "collection section update successful",
            "data"      => $results
        ]);
    }

    public function delete_section(DeleteSectionRequest $request)
    {
        $validated = $request->all();

        $collection_id = Arr::pull($validated, 'collection_id');
        $sections_id = Arr::pull($validated, 'id');

        try {
            $this->CheckUploadedAnswersCount($collection_id);

            DB::beginTransaction();

            collect($sections_id)->map(function ($item) use ($collection_id) {

                $sections = CollectionSections::where(['collection_id' => $collection_id, 'id' => $item])->firstOrFail();
                $sections->forceDelete();
            });

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "collection section delete successful"
            ]);
        } catch (\Exception $e) {
            // do task when error
            return response()->json([
                "status"  => 500,
                "message" => "collection section delete unsuccessful" . $e->getMessage(),
                "error"   => strval($e)
            ], 500);
        }
    }

    public function delete(DeleteCollectionsRequest $request)
    {
        DB::beginTransaction();
        try {
            Collections::whereIn('id', $request->id)->get()
                ->each(fn ($collection) => $collection->delete());
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "collection delete unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
        DB::commit();
        return response()->json([
            "status"  => 200,
            "message" => "collection delete successful"
        ]);
    }

    private function CheckUploadedAnswersCount($collection_id)
    {
        $uploadAnswersCount = CompetitionLevels::with(['participantsAnswersUploaded'])->where('collection_id', $collection_id)->get()->pluck('participantsAnswersUploaded')->flatten()->count();
        $uploadAnswersCount == 0 ?:  abort(403, 'Unauthorized action, Answers have been uploaded to collection');;
    }

    public function duplicate(Request $request, Collections $collection)
    {
        DB::beginTransaction();
        try {
            (new DuplicateCollectionService($request, $collection))->duplicate();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "collection duplicate unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }
        DB::commit();
        return response()->json([
            "status"  => 200,
            "message" => "collection duplicate successful"
        ]);
    }

    public function verify(Request $request)
    {
        if (!auth()->user()->hasRole(['super admin', 'admin'])) {
            return response()->json([
                "status"  => 403,
                "message" => "Only admins can verify collection"
            ]);
        }

        $competitionId = $request->validate([
            'competition_id' => ["required", Rule::exists('competition', 'id')->where('status', 'active')],
        ])['competition_id'];

        $competition = Competition::with(['rounds.levels.collection'])
            ->find($competitionId);

        $collectionId = $request->validate([
            'collection_id' => ['required', Rule::in($competition->rounds->pluck('levels.*.collection.id')->flatten()->toArray())],
        ])['collection_id'];

        $collection = Collections::findorfail($collectionId);
        if ($collection->status == Collections::STATUS_VERIFIED) {
            return response()->json([
                "status"  => 500,
                "message" => "Collection already verified !"
            ], 500);
        }
        $allTasksVerified = $this->checkCollectionTasksIsVerified($collection);
        if (!$allTasksVerified) {
            return response()->json([
                "status"  => 500,
                "message" => "All tasks of this collection must be verified first"
            ], 500);
        }
        $collection->status = Collections::STATUS_VERIFIED;
        $collection->save();
        return $this->competitionCollectionVerify($competitionId);
        return response()->json([
            "status"  => 200,
            "message" => "collection verified successfully"
        ]);
    }

    public function competitionCollectionVerify($competitionId)
    {
        $all_collections_verified = true;
        $competition = Competition::with(['rounds.levels.collection'])
            ->find($competitionId);
        $roundLevelPairs = [];

        foreach ($competition->rounds as $round) {
            foreach ($round->levels as $level) {
                $collection = $level->collection;
                $roundId = $round->id;
                $levelId = $level->id;
                $roundLevelPairs[] = [
                    'round_id' => $roundId,
                    'level_id' => $levelId,
                    'collection_status' => $collection->status
                ];
            }
        }

        foreach ($roundLevelPairs as $item) {
            if ($item['collection_status'] !== Collections::STATUS_VERIFIED || !$this->checkDifficultyIsVerified($item['round_id'], $item['level_id'], $competition->id)) {
                $all_collections_verified = false;
                break; // No need to continue checking if one collection is not verified
            }
        }

        if ($all_collections_verified) {
            $competition->update(['is_verified' => true]);
        }

        return $all_collections_verified;
    }


    public function checkCollectionTasksIsVerified($collection)
    {
        $sections = $collection->sections;
        foreach ($sections as $section) {
            $sectionTasks = $section->getSectionTaskAttribute();
            foreach ($sectionTasks as $task) {
                if ($task->status !== Tasks::STATUS_VERIFIED) {
                    return false;
                }
            }
        }
        return true;
    }

    public function difficultyAndPointsOverview(Request $request)
    {
        $competitionId = $request->validate([
            'competition_id' => ["required", Rule::exists('competition', 'id')->where('status', 'active')],
        ])['competition_id'];

        $competition = Competition::with(['rounds.levels.collection.sections', 'taskDifficulty'])
            ->find($competitionId);

        try {
            $rounds = [];
            $roundData = [];
            foreach ($competition->rounds as $round) {
                foreach ($round->levels as $level) {
                    $roundData = [
                        'round_id' => $round->id,
                        'round_name' => $round->name,
                        'level_id' => $level->id,
                        'level_name' => $level->name,
                        'collection_verified' => $level->collection->status == Collections::STATUS_VERIFIED,
                    ];

                    $eagerload = [
                        'reject_reason:reject_id,reason,created_at,created_by_userid',
                        'reject_reason.user:id,username',
                        'reject_reason.role:roles.name',
                        'tags:id,name',
                        'gradeDifficulty',
                        'sections',
                    ];

                    $collectionModel = Collections::with($eagerload)
                        ->AcceptRequest(['status', 'id', 'name', 'identifier']);

                    $collection = $collectionModel
                        ->find($level->collection->id);

                    $collectionData = collect($collection)
                        ->except(['updated_at', 'created_at', 'reject_reason', 'last_modified_userid', 'created_by_userid']);

                    $roundData['difficulty_and_points_verified'] = $this->checkDifficultyIsVerified($roundData['round_id'], $roundData['level_id'], $competition->id);
                    $roundData['collection'] =  $collectionData;
                    $rounds[] = $roundData;
                }
            }

            return response()->json([
                'status' => 200,
                'message' => 'competition collections retrieved successfully',
                'data' => [
                    'competition_id' => $competition->id,
                    'competition_name' => $competition->name,
                    'competition_data' => $rounds

                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'error in retrieving competition collections: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
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
