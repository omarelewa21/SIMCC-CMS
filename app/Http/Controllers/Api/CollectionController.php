<?php

namespace App\Http\Controllers\Api;

use App\Helpers\General\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\AddSectionsRequest;
use App\Models\Collections;
use App\Models\CollectionSections;
use App\Http\Requests\Collection\ApproveCollectionRequest;
use App\Http\Requests\Collection\CollectionListRequest;
use App\Http\Requests\Collection\DeleteCollectionsRequest;
use App\Http\Requests\Collection\RejectCollectionRequest;
use App\Http\Requests\Collection\UpdateCollectionRecommendationsRequest;
use App\Http\Requests\Collection\UpdateCollectionSectionRequest;
use App\Http\Requests\Collection\UpdateCollectionSettingsRequest;
use App\Http\Requests\Collection\CreateCollectionRequest;
use App\Http\Requests\Collection\DeleteSectionsRequest;
use App\Services\CollectionsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public function list(CollectionListRequest $request)
    {
        try{
            $collections = CollectionsService::getCollectionListCollection($request);
            $filterOptions = CollectionsService::getCollectionListFilterOptions($collections);
            if($request->has('competition_id') || $request->has('tag_id') ) {
                $collections = $this->filterCollectionList($collections, [
                    "1,competitions"    => $request->competition_id ?? false,
                    "1,tags"            => $request->tag_id ?? false
                ]);
            }
            $collectionsList = CollectionHelper::searchCollection(
                $request->search ? $request->search : null,
                $collections,
                array("identifier", "name", "description"),
                $request->limits ? $request->limits : 10
            );
            return response()->json([
                "status"    => 200,
                "data"      => array(
                    "filterOptions"     => $filterOptions,
                    'collectionList'    => $collectionsList
                )
            ]);
        } catch(\Exception $e) {
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
            $settings = collect($request->all())
                ->map(function ($setting) use (&$tags, &$recommendation, &$sections) {
                    $tags[] = Arr::pull($setting, 'tags');
                    $recommendation[] = Arr::pull($setting, 'recommendations');
                    $sections[] = $setting['sections'];
                    unset($setting['sections']);
                    return $setting;
                })->pluck('settings')->toArray();

           auth()->user()->collection()->createMany($settings)->pluck('id')->map(function ($item,$collectionIndex) use (&$tags,&$recommendation,&$sections) {
                $collection = Collections::find($item);
                count($tags) == 0 ?: $collection->tags()->sync($tags[$collectionIndex]);

                // add recommendation of grade and difficulty to collection
                if(count($recommendation[$collectionIndex]) > 0 ) {
                    collect($recommendation[$collectionIndex])->map(function ($item) use ($collection,$collectionIndex) {
                        $collection->gradeDifficulty()->create(
                            [
                                "grade" => Str::after($item['grade'], (intval(($collectionIndex+1) / 10)+1)),
                                "difficulty" => $item['difficulty']
                            ]);
                    });
                }

                collect($sections[$collectionIndex])->map(function ($section) use ($collection) {
                    $section['tasks'] =  Arr::pull($section, 'groups');
                    $collection->sections()->create($section)->id;
                });
            });

        } catch(\Exception $e){
            return response()->json([
                "status"  => 500,
                "message" => "collection create unsuccessful",
                "error"   => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"  => 200,
            "message" => "collection create successful"
        ]);
    }

    public function updateSettings(UpdateCollectionSettingsRequest $request)
    {
        DB::beginTransaction();
        try {
            $collection = Collections::findOrFail($request->collection_id);
            $settings = $request->settings;
            if($collection->allowedToUpdateAll()){
                $collection->update($settings);
            }else{
                $collection->update([
                    'time_to_solve'     => $settings['time_to_solve'],
                    'initial_points'    => $settings['initial_points'],
                    'description'       => $settings['description']
                ]);
            }
            if(Arr::has($settings, 'tags') && count($settings['tags']) > 0){
                $collection->tags()->sync($settings['tags']);
            }

        } catch(\Exception $e){
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

    public function updateRecommendations(UpdateCollectionRecommendationsRequest $request)
    {
        DB::beginTransaction();
        try {
            $collection = Collections::findOrFail($request->collection_id);
            $collection->gradeDifficulty()->delete();
            if(count($request->recommendations) > 0 ) {
                collect($request->recommendations)->map(function ($item) use ($collection) {
                    $collection->gradeDifficulty()->create(
                        [
                            "grade"         => $item['grade'],
                            "difficulty"    => $item['difficulty']
                        ]);
                });
            }
        } catch(\Exception $e){
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

    public function addSections(AddSectionsRequest $request)
    {
        DB::beginTransaction();
        try {
            $section = CollectionSections::create([
                "collection_id" => $request->collection_id,
                "description"   => $request->section['description'] ?? null,
                "tasks"         => $request->section['groups'],
                "allow_skip"    => $request->section['allow_skip'],
                "sort_randomly" => $request->section['sort_randomly']
            ]);

        } catch(\Exception $e){
            return response()->json([
                "status"  => 500,
                "message" => "Operation unsuccessfull",
                "error"   => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "Section added Successfully",
            "data"      => $section
        ]);
    }

    public function updateSections(UpdateCollectionSectionRequest $request)
    {
        DB::beginTransaction();
        try {
            $section = $request->section;
            $section['tasks'] = Arr::pull($section, 'groups');
            CollectionsService::CheckUploadedAnswersCount($request->collection_id);

            if($request->has('section_id')) {
                $results = CollectionSections::findOrFail($request->section_id);
                $results->update($section);
            } else {
                $section['collection_id'] = $request->collection_id;
                $results = CollectionSections::create($section);
            }

        } catch(\Exception $e){
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

    public function deleteSection(DeleteSectionsRequest $request)
    {
        DB::beginTransaction();
        try {
            CollectionsService::CheckUploadedAnswersCount($request->collection_id);
            collect($request->id)->map(function ($id) use($request) {
                $sections = CollectionSections::where(['collection_id' => $request->collection_id, 'id' => $id])->firstOrFail();
                $sections->forceDelete();
            });

        } catch(\Exception $e){
            return response()->json([
                "status"    => 500,
                "message"   => "Section delete unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"  => 200,
            "message" => "collection section delete successful"
        ]);
    }

    public function delete(DeleteCollectionsRequest $request)
    {
        DB::beginTransaction();
        try {
            Collections::whereIn('id', $request->id)->get()
                ->each(fn($collection) => $collection->delete());

        } catch(\Exception $e){
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

    public function approve(ApproveCollectionRequest $request)
    {
        try {
            Collections::whereIn('id', $request->ids)->update([
                'status'                => 'Active',
                'approved_by_userid'    => auth()->id(),
                'last_modified_userid'  => auth()->id()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Collections approval operation not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }

        return response()->json([
            "status"    => 200,
            "message"   => "Collections approved successfully"
        ]);
    }

    public function reject(RejectCollectionRequest $request, Collections $collection)
    {
        try {
            DB::transaction(function () use($collection, $request) {
                $collection->rejectReasons()->create([
                    'reason'            => $request->reason,
                    'created_by_userid' => auth()->id()
                ]);
                $collection->status = 'Rejected';
                $collection->save();
            });

        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Collection rejection operation not successfull",
                "error"     => $e->getMessage()
            ], 500);
        }

        return response()->json([
            "status"    => 200,
            "message"   => "Collection Rejected successfully"
        ]);
    }
}
