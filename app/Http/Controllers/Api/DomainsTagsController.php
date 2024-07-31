<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\DomainsTags;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\Tags\CreateDomainTagRequest;
use App\Http\Requests\Tags\TagsListRequest;
use App\Http\Requests\UpdateTagStatusRequest;
use App\Services\Tags\CreateTagService;
use App\Services\Tags\TagsListService;

class DomainsTagsController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(CreateDomainTagRequest $request)
    {
        DB::beginTransaction();

        try {
            collect($request->all())->each(function ($row) {
                if(isset($row['is_tag']) && $row['is_tag'] == 1){
                    CreateTagService::createTag($row);
                }
                elseif(isset($row['domain_id']) && $row['domain_id'] != null) {
                    CreateTagService::createTopics($row);
                }
                else {
                    CreateTagService::createDomain($row);
                }
            });

        } catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => 'Operation Error' . $e->getMessage(),
                'error'     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            'status'    => 201,
            'message'   => 'add domain/topic/tag successful'
        ], 201);
    }

    public function oldList (Request $request) {

        try {
            $vaildate = $request->validate([
                'domain_id' =>  ['integer',Rule::exists('domains_tags', "id",)
                    ->where("domain_id", NULL)
                    ->where("is_tag", 0)
                ],
                'status' => 'alpha',
                'limits' => 'integer|min:10|max:500',
                'page' => 'integer',
                'search' => 'max:255'
            ]);


            $limits = $request->limits ? $request->limits : 500;

            if(Route::currentRouteName() == "tag.list" ) {
                $limits = 99999;
            }

            $searchKey = isset($vaildate['search']) ? $vaildate['search'] : null;

            $domainTagModel = DomainsTags::with(['domain','created_by','modified_by'])->AcceptRequest(['status', 'domain_id']);

            $returnFiltered = $domainTagModel
                ->where('status', '!=', 'deleted')
                ->filter()
                ->get();

            $domainTagCollection = collect($returnFiltered)->map(function ($item) { // match country id and add country name into the collection
                $item['created_by_username'] = isset($item['created_by']['username']) ? $item['created_by']['username'] : null ;
                $item['modified_by_username'] = !empty($item['modified_by']) ? $item['modified_by']['username'] : null;
                $item['domain_name'] = !empty($item['domain']) ? $item['domain']['name'] : null;
                $item['domain_status'] = !empty($item['domain']) ? $item['domain']['status'] : null;

                unset($item['domain']);
                unset($item['created_by']);
                unset($item['modified_by']);
                return $item;
            })->filter(function ($value) {
               return $value['domain_status'] != 'deleted';
            });

            /**
             * Lists of availabe filters
             */
            $temp = '';
            $availDomainStatus = $domainTagCollection->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            $availDomain = $domainTagCollection->filter(function ($item) {
                return $item['domain_id'] == null && $item['is_tag'] == 0;
            })->unique()->values()->map(function ($row) {
                return ["id" => $row['id'], "name" => $row['name']];
            });

            /**
             * EOL Lists of availabe filters
             */

            $availForSearch = array("name");
            $tagList = CollectionHelper::searchCollection($searchKey, $domainTagCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availDomainStatus, 'Domain' => $availDomain], "tagLists" => $tagList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        }
        catch(QueryException $e) {
            return response()->json([
                "status" => 500,
                "message" => "Retrieve tag retrieve unsuccessful" . $e->getMessage(),
                "error" => strval($e)
            ]);
        }
        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve tag retrieve unsuccessful" . $e->getMessage(),
                "error" => strval($e)
            ]);
        }
    }

    public function List (TagsListRequest $request)
    {
        return encompass(
            fn () => (new TagsListService($request))->getWhatUserWants()
        );
    }

    public function update (Request $request) {

        $request->validate([
            "id" => "required|integer",
            "name" => "required|regex:/^[\.\,\s\(\)\[\]\w-]*$/|max:255",
        ]);

        try {

            $tag = DomainsTags::findOrFail($request->id);
            $tag->name = $request->name;
            $tag->last_modified_userid = auth()->user()->id;
            $tag->save();

            return response()->json([
                "status" => 200 ,
                "message" => "Tag update successful",
                "data" => $tag
            ]);
        }

        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Tag update unsuccessful" . $e->getMessage(),
                "error" => strval($e)
            ]);
        }
    }

    public function delete(UpdateTagStatusRequest $request)
    {
        DB::beginTransaction();
        try {
            DomainsTags::whereIn('id', $request->id)->get()
                ->each(fn($domainTag) => $domainTag->delete());

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status"    => 500,
                "message"   => "Delete unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "Deleted successfully"
        ]);
    }

    public function approve(UpdateTagStatusRequest $request)
    {
        DB::beginTransaction();

        try {
            DomainsTags::whereIn("id", $request->id)
                ->whereNotIn("status", ["active", "deleted"])
                ->update([
                    'approved_by_userid'    => auth()->user()->id,
                    'last_modified_userid'  => auth()->user()->id,
                    'status'                => 'active'
                ]);

        } catch(\Exception $e) {
            DB::rollback();
            return response()->json([
                "status"    => 500,
                "message"   => "Error encountered while trying to approve" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "Tag approved successfully"
        ]);
    }
}
