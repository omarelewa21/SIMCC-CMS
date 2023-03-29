<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\User;
use App\Models\School;
use App\Models\Countries;
use App\Rules\CheckSchoolUnique;


/**
 *
 */
class SchoolController extends Controller
{

    public function create (Request $request) {

        $countries = Countries::get()->pluck('id');
        $counter = 0;

        $request['role_id'] = auth()->user()->role_id;

        $validated = $request->validate([
            "role_id" => "nullable",
            "school.*.country_id" => ['exclude_if:role_id:2,4','required_if:role_id,1','integer',Rule::in($countries)],
            "school.*.name" => ["required","string",new CheckSchoolUnique, Rule::notIn(['Organization School','ORGANIZATION SCHOOL','organization school'])],
            "school.*.private" => "required|boolean",
            "school.*.address" => "max:255",
            "school.*.postal" => "max:255",
            "school.*.phone" => "required|regex:/^[0-9]*$/",
            "school.*.email" => "required|email",
            "school.*.province" => "required|max:255",
        ]);

        for($i=0;$i<count($validated['school']);$i++) {

            $validated['school'][$i] = [
                ...$validated['school'][$i],
                "country_id" => in_array(auth()->user()->role_id,[2,4]) ? auth()->user()->country_id : $validated['school'][$i]['country_id'],
                "created_by_userid" => auth()->user()->id,
                "created_at" => date('Y-m-d H:i:s'),
                "status" => in_array(auth()->user()->role_id,[2,4]) ? "pending" : "active" // if this is admin role, set school status to active
            ];
        }

        try{
            School::insert($validated['school']);

            return response()->json([
                "status" => 201,
                "message" => "Schools create successful"
            ]);

        }
        catch (\Exception $e) {

            return response()->json([
                "status" => 500,
                "message" => "Create school unsuccessful"
            ]);

        }
    }

    public function update (UpdateSchoolRequest $request)
    {
        try {
            $editOwnRoles = ['Country Partner', 'Teacher', 'Country Partner Assistant', 'School Manager'];
            $school = School::whereId($request->id)->where('status', '!=', 'deleted')->firstOrFail();
            $user = auth()->user();

            if($user->hasRole($editOwnRoles)) {
                if(in_array($school->status, ['pending', 'rejected'])){
                    if($user->hasRole(['Country Partner', 'Country Partner Assistant'])){
                        $idAllowed = User::where(['organization_id' => $user->organization_id,'country_id' => $user->country_id])
                                        ->whereIn('role_id', [2,4])->pluck('id')->toArray();
                            $idAllowed[] = $user->id;
                            if (!in_array($school->created_by_userid, $idAllowed))  {
                                return response()->json([
                                    "status"  => 401,
                                    "message" => "School update unsuccessful, only allowed to edit pending school created by country partner or assistance"
                                ], 401);
                            };
                    }
                    if ($request->filled($request->name)) $school->name = $request->name;
                    if ($request->filled($request->province)) $school->province = $request->province;

                } else {
                    if(($user->hasRole(['Country Partner', 'Country Partner Assistant'])) && ($request->has('$request->name') ||  $request->has('province')) ) {
                        if($request->filled('name')) $school->name = $request->name;
                        if($request->filled('province')) $school->province = $request->province;
                        $school->status = 'pending';
                    }
                }

            } else {
                $school->status = "active";
            }

            if($user->hasRole(['Super Admin', 'Admin']))  {
                $school->name = $request->name ?? $school->name;
                $school->province = $request->province ?? $school->province;
            }

            $school->address = $request->address;
            $school->email = $request->email;
            $school->phone = $request->phone;
            $school->postal = $request->postal;
            $school->last_modified_userid = $user->id;
            $school->save();

            return response()->json([
                "status"    => 200,
                "message"   => "School update successful",
                "data"      => $school
            ]);

        }
        catch(\Exception $e){
            return response()->json([
                "status"    => 500,
                "message"   => "School update unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function list (Request $request) {

        $vaildate = $request->validate([
            'id' => "integer",
            'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            'status' => 'alpha',
            'country_id' => 'integer',
            'private' => 'boolean',
            'limits' => 'integer',
            'page' => 'integer',
            'show_teachers' => 'boolean',
            'search' => 'max:255'
        ]);

        try {
            if($request->limits == "0") {
                $limits = 99999999;
            } else {
                $limits = $request->limits ?? 10; //set default to 10 rows per page
            }

            $searchKey = isset($vaildate['search']) ? $vaildate['search'] : null;
            $countries = Countries::all()->keyBy('id')->toArray();
            $eagerload = [
                'created_by',
                'modified_by',
                'approved_by',
                'reject_reason:reject_id,reason,created_at,created_by_userid',
                'reject_reason.user:id,username',
                'reject_reason.role:roles.name',
                'teachers'
            ];

            $schoolModel = School::has('organization','=',0)
                ->with($eagerload)
                ->AcceptRequest(['status', 'country_id', 'name', 'private']);

            switch(auth()->user()->role_id) {
                case 2:
                case 4:
                    $schoolModel->where("country_id", auth()->user()->country_id)->where('status', '!=', 'deleted');
                    break;
                case 3:
                case 5:
                    $schoolModel->where("id", auth()->user()->school_id)->where('status', '!=', 'deleted');;
                    break;
            }

            $returnFiltered = $schoolModel
                ->filter()
                ->get();

            $schoolCollection = collect($returnFiltered)->map(function ($item) use ($countries) { // match country id and add country name into the collection

                $item['country_name'] = $countries[$item['country_id']]['display_name'];
                $item['created_by_username'] = $item['created_by']['name'];
                $item['modified_by_username'] = !empty($item['modified_by']) ? $item['modified_by']['username'] : null;
                $item['approved_by_username'] = !empty($item['approved_by']) ? $item['approved_by']['username'] : null;
                $item['rejected_by_username'] = !empty($item['rejected_by']) ? $item['rejected_by']['username'] : null;


//                unset($item['created_by_userid']);
                unset($item['last_modified_userid']);
                unset($item['approved_by_userid']);
                unset($item['rejected_by_userid']);
                unset($item['deleted_by_userid']);
                unset($item['created_by']);
                unset($item['modified_by']);
                unset($item['approved_by']);
                unset($item['rejected_by']);

                return $item;
            });



            /**
             * Lists of availabe filters
             */
            $availSchoolStatus = $schoolCollection->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            $availCountry = $schoolCollection->map(function ($item) {
                return ["id" => $item['country_id'], "name" => $item['country_name']];
            })->unique()->values();
            $availSchoolType = $schoolCollection->map(function ($item) {
                return $item['private'];
            })->unique()->values();
            /**
             * EOL Lists of availabe filters
             */

            $availForSearch = array("name", "province", "address", "postal", "phone");
            $schoolList = CollectionHelper::searchCollection($searchKey, $schoolCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availSchoolStatus, 'countries' => $availCountry, 'schooltype' => $availSchoolType], "SchoolLists" => $schoolList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        }

        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve school unsuccessful" .$e
            ]);
        }

    }

    public function reject (Request $request) {

        $vaildated = $request->validate([
            "id" => "required|array",
            "id.*" => ['required','integer',Rule::exists('schools','id')->whereNotIn('created_by_userid',[auth()->user()->id])],
            "reject_reason" => "required|array",
            "reject_reason.*" => 'nullable|regex:/[a-zA-Z0-9\s]+/'
        ]);

        return $this->_updateStatus($vaildated,"rejected", "rejected,deleted,active");
    }

    public function approve (Request $request) {

        $vaildated = $request->validate([
            "id" => "required|array",
            "id.*" => ['required','integer',Rule::exists('schools','id')->whereNotIn('created_by_userid',[auth()->user()->id])],
//            "id.*" => ['required','integer','exists:schools,id'],
        ]);


        return $this->_updateStatus($vaildated,"active", "active,deleted");
    }

    public function undelete (Request $request) {

        $vaildated = $request->validate([
            "id" => "required|array",
            "id.*" => ['required','integer',Rule::exists('schools','id')],
        ]);

        return $this->_updateStatus($vaildated,"active", "active,pending");
    }

    public function delete (Request $request) {

        $vaildated = $request->validate([
            "id" => "array|array",
            "id.*" => ['required','integer',Rule::exists('schools','id')],
        ]);

        return $this->_updateStatus($vaildated,"deleted", "deleted");
    }

    private function _updateStatus ($vaildated, $status, $exclude) {

        $userId = auth()->user()->id;

        $reject_reason = isset($vaildated["reject_reason"][0]) ? $vaildated["reject_reason"][0] : null;
        $update = ["status" => $status];

        if(Route::currentRouteName() == "school.approve")
        {
            $schoolCount = School::whereIn("id",$vaildated['id'])
                ->where([
                    ['created_by_userid', '!=', $userId],
                    ['status', '=', 'pending']
                ])
                ->count();

            if($schoolCount !== count($vaildated['id'])) {
                return response()->json([
                    "status" => 401,
                    "message" => "School, Unable to approve.",
                ]);
            }

            $update["approved_by_userid"] = $userId;
            $update['status'] = 'active';
            $notificationBody = 'School, Has been approved';
        }

        if(Route::currentRouteName() == "school.delete")
        {
            DB::beginTransaction();
            $hardDeleted = School::whereIn('id',Arr::collapse($vaildated))->whereIn('status',['pending','rejected'])->forceDelete();
            DB::commit();
            if($hardDeleted > 0) {
                return response()->json([
                    "status" => 200,
                    "message" => "School status update successful"
                ]);
            }

            if (in_array(auth()->user()->role_id,[2,4])) {
                return response()->json([
                    "status" => 500,
                    "message" => "The selected school id is invalid."
                ]);
            }

            $update['status'] = 'deleted';
            $notificationBody = ' School, Has been deleted';
        }

        if(Route::currentRouteName() == "school.undelete")
        {
            $update['status'] = 'active';
            $notificationBody = ' School, Has been recover from deleted';
        }

        if(Route::currentRouteName() == "school.reject")
        {
            $update['status'] = 'rejected';
            $notificationBody = 'School, Has been rejected, please edit school info and submit again';
        }

        $update["last_modified_userid"] = $userId;
        $update["updated_at"] = Carbon::today()->format('Y-m-d h:i:s');

        try {
            DB::beginTransaction();

            // Start the transaction
            $school =  DB::table("schools")
                ->whereIn("id", $vaildated["id"])
                ->whereNotIn ("status",[$exclude]);

            $result = $school->update($update);

            if($reject_reason) {
                School::find($vaildated["id"][0])->reject_reason()->create([
                    'reason' => $reject_reason,
                    'created_by_userid' => auth()->user()->id
                ]);
            }

            if($result != count($vaildated['id']))
            {
                DB::rollback();

                return response()->json([
                    "status" => 500,
                    "message" => "$status unsuccessful"
                ]);
            }

            DB::commit();

            if(isset($notificationBody)) {  //send notification to the user that create the school entry
                $info = $school->get(['created_by_userid','name']);

                foreach($info as $row) {
                    $user = User::find($row->created_by_userid);
                    $schoolName = $row->name;

                    $data = [
                        'body' =>  [
                            "page" => "school",
                            "message" => "Pending - $schoolName $notificationBody",
                            "status" => 200,
                        ]
                    ];

                    Notification::send($user, new SendNotification($data));
                }
            }

            return response()->json([
                "status" => 200,
                "message" => "School status update successful"
            ]);

        } catch(\Exception $e) {
            DB::rollback();

            return response()->json([
                "status" => 500,
                "message" => "School status update unsuccessful"
            ]);
        }
    }

}
