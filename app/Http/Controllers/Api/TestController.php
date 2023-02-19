<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Notification;
use App\Notifications\SendNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Helpers\General\CollectionHelper;
use Illuminate\Support\Arr;
use App\Models\User;
use App\Models\School;
use App\Models\Countries;


class TestController extends Controller
{
    public function create(Request $request)
    {

        $countries = Countries::select('id')->get()->map(function ($row) {
            return $row->id;
        });

        $requestCounter = 0;

        $validated = $request->validate([
            "*.country_id" => ['required', 'integer', Rule::in($countries)],
            "*.name" => ["required", "distinct", "regex:/^[\'\;\.\,\s\(\)\[\]\w-]*$/", Rule::unique("schools")->where(function ($query) use (&$requestCounter, $request) {
                $query->where('name', $request[$requestCounter]['name'])->where('country_id', $request[$requestCounter]['country_id']); //make sure only 1 unique school name per country
                $requestCounter += 1;
            })],
            "*.private" => "required|boolean",
            "*.address" => "max:255",
            "*.postal" => "max:255",
            "*.phone" => "required|integer",
            "*.email" => "required|email",
            "*.province" => "max:255",
        ]);

        for ($i = 0; $i < count($validated); $i++) {

            if (auth()->user()->role_id == 2) { // if this is country partner, set school status to pending
                $validated[$i]["country_id"] = auth()->user()->country_id;
            }

            $validated[$i]["created_by_userid"] = auth()->user()->id; //assign entry creator user id
            $validated[$i]["created_at"] = date('Y-m-d H:i:s');
            $validated[$i]["status"] = "pending";
        }

        try {
            School::insert($validated);

            return response()->json([
                "status" => 201,
                "message" => "Schools create successful"
            ]);
        } catch (ModelNotFoundException $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Create school unsuccessful"
            ]);
        } catch (QueryException $e) {
            return response()->json([
                "status" => 500,
                "message" => "Create school unsuccessful",
            ]);
        }
//
//        catch (\Exception $e) {
//            return response()->json([
//                "status" => 500,
//                "message" => "Create school unsuccessful"
//            ]);
//        }
    }

    public function update(Request $request)
    {

        $request->validate([
            "id" => "required|integer",
            "name" => "required|regex:/^[\.\,\s\(\)\[\]\w-]*$/|max:255",
            "address" => "max:255",
            "postal" => "integer",
            "phone" => "required|integer",
            "email" => "required|integer",
            "province" => "max:255",
        ]);

        try {
            $viewEditOwn = auth()->user()->role_id == 2 || auth()->user()->role_id == 3 ? true : false; //return true if role is country partner and teacher

            $school = School::findOrFail($request->id);

            if ($viewEditOwn) {
                if ($school->country_id != auth()->user()->country_id) { // country and teacher role unable to edit school from other country
                    return response()->json([
                        "status" => 401,
                        "message" => "School update unsuccessful, only allowed to edit school's from current country"
                    ]);
                } elseif (auth()->user()->role_id == 3 && auth()->user()->school_id != $school->id) {
                    return response()->json([
                        "status" => 401,
                        "message" => "School update unsuccessful, only allowed to edit current school"
                    ]);
                }

                if (($school->status == "pending" || $school->status == "rejected") && $school->created_by_userid == auth()->user()->id) {
                    $school->name = $request->name; // country partner able to change school name during pending status
                    $school->status = "pending"; // country partner able to change school name during pending status
                }
            } else {
                $school->name = $request->name;
                $school->status = "active";
                $school->last_modified_userid = auth()->user()->id;
            }

            $school->address = $request->address;
            $school->phone = $request->phone;
            $school->postal = $request->postal;
            $school->province = $request->province;

            $school->save();

            return response()->json([
                "status" => 200,
                "message" => "School update successful",
                "data" => $school
            ]);
        } catch (ModelNotFoundException $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "School update unsuccessful"
            ]);
        }
    }

    public function list(Request $request)
    {

        $vaildate = $request->validate([
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

            $limits = $request->limits ? $request->limits : 10;
            $searchKey = isset($vaildate['search']) ? $vaildate['search'] : null;

            $countries = Countries::all()->keyBy('id')->toArray();
            $eagerload = ['created_by', 'modified_by', 'approved_by', 'reject_reason:reject_id,reason,created_at,user_id', 'reject_reason.user:id,username', 'reject_reason.role:roles.name'];

            if ($request->show_teachers == 1) {
                $eagerload[] = 'teachers';
            }

            $schoolModel = School::has('partners', '=', 0)
                ->with($eagerload)
                ->AcceptRequest(['status', 'country_id', 'name', 'private']);

            if (auth()->user()->role_id != 1) { //if role is not admin, filter by their user respective account
                $schoolModel = $schoolModel->where("country_id", auth()->user()->country_id)->where('status', '!=', 'deleted');
            }

            $returnFiltered = $schoolModel
                ->filter()
                ->get();

            $schoolCollection = collect($returnFiltered)->map(function ($item) use ($countries) { // match country id and add country name into the collection

                $item['country_name'] = $countries[$item['country_id']]['display_name'];
                $item['created_by_username'] = $item['created_by']['username'];
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
        } catch (ModelNotFoundException $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve school unsuccessful"
            ]);
        }

    }

    public function reject(Request $request)
    {

        $validated = $request->validate([
            "id" => "array",
            "id.*" => ['required', 'integer', Rule::exists('schools', 'id')->whereNotIn('created_by_userid', [auth()->id()])],
            "reject_reason" => "array",
            "reject_reason.*" => 'required|regex:/[a-zA-Z0-9\s]+/'
        ]);

        return $this->_updateStatus($validated, "rejected", "rejected,deleted,active");
    }

    public function approve(Request $request)
    {

        $validated = $request->validate([
            "id" => "array",
            "id.*" => ['required', 'integer', Rule::exists('schools', 'id')->whereNotIn('created_by_userid', [auth()->user()->id])],
//            "id.*" => ['required','integer','exists:schools,id'],
        ]);

        return $this->_updateStatus($validated, "active", "active,deleted");
    }

    public function undelete(Request $request)
    {

        $validated = $request->validate([
            "id" => "array",
            "id.*" => ['required', 'integer', Rule::exists('schools', 'id')],
        ]);

        return $this->_updateStatus($validated, "active", "active,pending");
    }

    public function delete(Request $request)
    {
        if ($request->delete == 1) {

            $validated = $request->validate([
                'id' => 'array',
                'id.*' => 'integer'
            ]);

            try {
                $result = School::destroy($validated['id']);

                if ($result) {
                    return response()->json([
                        "status" => 200,
                        "message" => $result . " school delete successful"
                    ]);
                }

            } catch (ModelNotFoundException $e) {
                // do task when error
                return response()->json([
                    "status" => 500,
                    "message" => "delete school unsuccessful"
                ]);
            }
        }

        $validated = $request->validate([
            "id" => "array",
            "id.*" => ['required', 'integer', Rule::exists('schools', 'id')],
        ]);

        return $this->_updateStatus($validated, "deleted", "deleted");
    }

    private function _updateStatus($validated, $status, $exclude)
    {

        $userId = auth()->user()->id;

        $reject_reason = isset($validated["reject_reason"][0]) ? $validated["reject_reason"][0] : null;
        $update = ["status" => $status];

        DB::beginTransaction();

        if (Route::currentRouteName() == "school.approve") {
            $schoolCount = School::whereIn("id", $validated['id'])
                ->where([
                    ['created_by_userid', '!=', $userId],
                    ['status', '=', 'pending']
                ])
                ->count();

            if ($schoolCount !== count($validated['id'])) {
                return response()->json([
                    "status" => 401,
                    "message" => "School, Unable to approve.",
                ]);
            }

            $update["approved_by_userid"] = $userId;
            $update['status'] = 'active';
            $notificationBody = 'School, Has been approved';
        }

        if (Route::currentRouteName() == "school.delete") {
            $update['status'] = 'deleted';
            $notificationBody = ' School, Has been deleted';
        }

        if (Route::currentRouteName() == "school.undelete") {
            $update['status'] = 'active';
            $notificationBody = ' School, Has been recover from deleted';
        }

        if (Route::currentRouteName() == "school.reject") {
            $update['status'] = 'rejected';
            $notificationBody = 'School, Has been rejected, please edit school info and submit again';
        }

        $update["last_modified_userid"] = $userId;
        $update["updated_at"] = Carbon::today()->format('Y-m-d h:i:s');

        try {

            // Start the transaction
            $school = DB::table("schools")
                ->where("id", $validated["id"])
                ->whereNotIn("status", [$exclude]);

            $result = $school->update($update);

            if ($reject_reason) {
                School::find($validated["id"][0])->reject_reason()->create([
                    'reason' => $reject_reason,
                    'user_id' => auth()->user()->id
                ]);
            }

            if ($result != count($validated['id'])) {
                DB::rollback();

                return response()->json([
                    "status" => 500,
                    "message" => "$status unsuccessful"
                ]);
            }

            DB::commit();

            if (isset($notificationBody)) {  //send notification to the user that create the school entry
                $info = $school->get(['created_by_userid', 'name']);

                foreach ($info as $row) {
                    $user = User::find($row->created_by_userid);
                    $schoolName = $row->name;

                    $data = [
                        'body' => [
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

        } catch (QueryException $e) {
            DB::rollback();

            return response()->json([
                "status" => 500,
                "message" => "School status update unsuccessful",
            ]);
        }
    }

}
