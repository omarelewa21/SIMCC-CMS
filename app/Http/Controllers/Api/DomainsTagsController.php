<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Notifications\SendNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\DomainsTags;
use App\Rules\CheckDomainTagsExist;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\CreateDomainTagRequest;
use App\Http\Requests\UpdateTagStatusRequest;

class DomainsTagsController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(CreateDomainTagRequest $request)
    {
        try {
            DB::beginTransaction();
            collect($request->all())->map(function ($row) {
                $row['created_by_userid'] = auth()->id();
                $row['status'] = auth()->user()->hasRole(['super admin', 'admin']) ? 'active' : 'pending';
                $row['deleted_at'] = null;
                if(isset($row['domain_id']) || $row['is_tag'] == 1) {
                    //insert new topics or tag
                    if(count($row['name']) > 0) {
                        foreach ($row['name'] as $item) {
                            $row['name'] = $item;
                            DomainsTags::withTrashed()->updateOrCreate([
                                'name'      => $row['name'],
                                'is_tag'    => $row['is_tag'],
                                'domain_id' => isset($row['domain_id']) ? $row['domain_id'] : null
                            ], $row);
                        }
                    }
                }

                if(!isset($row['domain_id']) && $row['is_tag'] == 0) {
                    //new domain with/without topics attach to it, need to create the domain first then insert topic with the new domain id
                    $domain = $row;
                    $domain["name"] = $domain["name"][0];                       //first element of name array is reserve for domain
                    $domain = DomainsTags::withTrashed()->updateOrCreate([
                        'name'      => $domain['name'],
                    ], $domain);
                    $row['domain_id'] = $domain->id;
                    $topics = $row['name'];

                    foreach ($topics as $topic) {
                        $row['name'] = $topic;
                        DomainsTags::withTrashed()->updateOrCreate([
                            'name'      => $row['name'],
                            'is_tag'    => $row['is_tag'],
                            'domain_id' => isset($row['domain_id']) ? $row['domain_id'] : null
                        ], $row);
                    }
                }
                DB::commit();

            });

            return response()->json([
                'status'    => 201,
                'message'   => 'add domain/topic/tag successful'
            ], 201);
        }
        catch (ModelNotFoundException $e){
             return response()->json([
                 'status'   => 404,
                 'message'  => 'add domain/topic/tag unsuccessful'
             ], 404);
        }
        catch (\Exception $e){
            return response()->json([
                'status'    => 500,
                'message'   => 'Unknown Error',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    public function list (Request $request) {

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

            if(route::currentRouteName() == "tag.list" ) {
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
                "message" => "Retrieve tag retrieve unsuccessful",
            ]);
        }
        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve tag retrieve unsuccessful"
            ]);
        }
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
                "message" => "Tag update unsuccessful"
            ]);
        }
    }

    public function delete(UpdateTagStatusRequest $request)
    {
        DB::beginTransaction();

        try {
            DomainsTags::whereIn('id', $request->id)
                ->update([
                    'status'                => 'deleted',
                    'last_modified_userid'  => auth()->id()
                ]);
            DomainsTags::whereIn('id', $request->id)->delete();

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status"    => 500,
                "message"   => "delete unsuccessful" . $e->getMessage(),
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "Tag deleted successfully"
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
                "error"     => $e->getMessage()
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "Tag approved successfully"
        ]);
    }
}

