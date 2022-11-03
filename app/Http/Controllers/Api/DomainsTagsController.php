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

class DomainsTagsController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            '*.is_tag' => 'required_if:*.domain_id,null|boolean',
            '*.domain_id' => 'integer|exclude_if:*.is_tag,1|exists:domains_tags,id' ,
            '*.name' => 'required|array',
            '*.name.*' => ['required','regex:/^[\.\,\s\(\)\[\]\w-]*$/',new CheckDomainTagsExist,Rule::unique('domains_tags','name')->whereNull('domain_id')],
        ]);

        try {
            DB::beginTransaction();

            $validated = collect($validated)->map(function ($row) use(&$id) {
                $row['created_by_userid'] = auth()->user()->id;
                $row['status'] = auth()->user()->role_id == 0 || auth()->user()->role_id == 1 ? 'active' : 'pending' ;

                if(isset($row['domain_id']) || $row['is_tag'] == 1) { //insert new topics or tag

                    if(count($row['name']) > 0) {
                        $temp = $row['name'];

                        foreach ($temp as $item) {
                            $row['name'] = $item;
                            DomainsTags::create($row);
                        }
                    }
                }

                if(!isset($row['domain_id']) && $row['is_tag'] == 0) { //new domain with/without topics attach to it, need to create the domain first then insert topic with the new domain id
                    $domain = $row;
                    $domain["name"] = $domain["name"][0]; //first element of name array is reserve for domain
                    $domainId = DomainsTags::create($domain)->id;
                    $row['domain_id'] = $domainId;
                    $topics = $row['name'];

                    foreach ($topics as $topic) {
                        $row['name'] = $topic;
                        DomainsTags::create($row);

                    }
                }

                DB::commit();

            });

            return response()->json([
                'status' => 201,
                'message' => 'add domain/topic/tag successful'
            ]);
        }
        catch (ModelNotFoundException $e){
             return response()->json([
                 'status' => 500,
                 'message' => 'add domain/topic/tag unsuccessful'
             ]);
        }
        catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'message' => 'Unknown Error'
            ]);
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
//        catch (\Exception $e) {
//            return response()->json([
//                "status" => 500,
//                "message" => "Retrieve users retrieve unsuccessful"
//            ]);
//        }
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

    public function delete (Request $request) {
        return $this->_updateStatus($request,"deleted", "deleted");
    }

    public function approve (Request $request) {
        return $this->_updateStatus($request,"active", "active,deleted");
    }

    private function _updateStatus ($request, $status, $exclude) {

        // {"id" : [xx,xx,xx]}
        $validated = $request->validate([
            "id" => "array",
            "id.*" => "required|integer|exists:domains_tags,id",
        ]);

        $userid = auth()->user()->id;

        if(Route::currentRouteName() == "approveTag")
        {
            $update["approved_by_userid"] = $userid;
            $update['status'] = 'active';
        }

        if(Route::currentRouteName() == "deleteTag") {
            $userid = auth()->user()->id;
            $update = ["status" => $status];
        }

        $update["last_modified_userid"] = $userid;

        try {
            DB::beginTransaction();

            // Start the transaction
            $domainTags =  DB::table("domains_tags")
                ->whereIn("id", $validated["id"])
                ->whereNotIn ("status",[$exclude]);

            $result = $domainTags->update($update);

            if($result != count($request->id))
            {
                DB::rollback();

                return response()->json([
                    "status" => 500,
                    "message" => "$status unsuccessful"
                ]);
            }

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "Tag status update successful"
            ]);

        } catch(QueryException $e) {
            DB::rollback();

            return response()->json([
                "status" => 500,
                "message" => "Tag status update unsuccessful" ,
            ]);
        }
    }
}

