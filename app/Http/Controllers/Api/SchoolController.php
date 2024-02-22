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
use App\Helpers\General\CollectionHelper;
use App\Http\Request\ApproveSchoolRequest;
use App\Http\Requests\CreateSchoolRequest;
use App\Http\Requests\RejectSchoolRequest;
use App\Http\Requests\SchoolListRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\User;
use App\Models\School;
use App\Models\Countries;
use Illuminate\Database\Eloquent\Builder;

class SchoolController extends Controller
{
    public function create(CreateSchoolRequest $request)
    {
        DB::beginTransaction();

        try {
            collect($request->school)->unique('name')
                ->each(function ($school) {
                    School::create($school);
                });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "Create school unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 201,
            "message"   => "Schools create successful"
        ]);
    }

    public function update(UpdateSchoolRequest $request)
    {
        try {
            $school = School::whereId($request->id)->where('status', '!=', 'deleted')->firstOrFail();
            $school->update([
                'status'    => auth()->user()->hasRole(['Super Admin', 'Admin']) ? 'active' : 'pending',
                'name'      => $request->name ?? $school->name,
                'name_in_certificate'      => $request->name_in_certificate ?? $school->name_in_certificate,
                'province'  => $request->province ?? $school->province,
                'address'   => $request->address ?? $school->address,
                'email'     => $request->email ?? $school->email,
                'phone'     => $request->phone ?? $school->phone,
                'postal'    => $request->postal ?? $school->postal,
                'private'    => auth()->user()->hasRole(['Super Admin', 'Admin', 'Country Partner', 'Country Partner Assistant']) ? $request->private : $school->private
            ]);

            return response()->json([
                "status"    => 200,
                "message"   => "School update successful",
                "data"      => $school
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "School update unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function list(SchoolListRequest $request)
    {
        try {
            if ($request->limits == "0") {
                $limits = 99999999;
            } else {
                $limits = $request->limits ?? 10; //set default to 10 rows per page
            }

            $searchKey = $request->search ?? null;
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

            $schoolModel = School::has('organization', '=', 0)
                ->with($eagerload)
                ->withCount('participants')
                ->AcceptRequest(['status', 'country_id', 'name', 'private']);

            switch (auth()->user()->role_id) {
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
                ->when($request->mode === 'csv', function (Builder $query) {
                    $query->join('all_countries', 'all_countries.id', 'schools.country_id')
                        ->selectRaw(
                            "CONCAT('\"',schools.name,'\"') as name,
                        CONCAT('\"',all_countries.display_name,'\"') as country,
                        schools.status,
                        schools.email,
                        CONCAT('\"',schools.address,'\"') as address,
                        CONCAT('\"',schools.postal,'\"') as postal,
                        CONCAT('\"',schools.province,'\"') as province,
                        schools.phone"
                        );
                })
                ->filter()
                ->get();

            if ($request->mode === 'csv') {
                return $returnFiltered;
            }

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
        } catch (\Exception $e) {
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve school unsuccessful" . $e
            ]);
        }
    }

    public function reject (RejectSchoolRequest $request) {

        return $this->_updateStatus($request->all(), "rejected", "rejected,deleted,active");
    }

    public function approve (ApproveSchoolRequest $request) {
        return $this->_updateStatus($request->all(), "rejected", "rejected,deleted,active");
    }

    public function undelete(Request $request)
    {

        $vaildated = $request->validate([
            "id" => "required|array",
            "id.*" => ['required', 'integer', Rule::exists('schools', 'id')],
        ]);

        return $this->_updateStatus($vaildated, "active", "active,pending");
    }

    public function delete(Request $request)
    {

        $vaildated = $request->validate([
            "id" => "array|array",
            "id.*" => ['required', 'integer', Rule::exists('schools', 'id')],
        ]);

        return $this->_updateStatus($vaildated, "deleted", "deleted");
    }

    private function _updateStatus($vaildated, $status, $exclude)
    {

        $userId = auth()->user()->id;

        $reject_reason = isset($vaildated["reject_reason"][0]) ? $vaildated["reject_reason"][0] : null;
        $update = ["status" => $status];

        if (Route::currentRouteName() == "school.approve") {
            $schoolCount = School::whereIn("id", $vaildated['id'])
                ->where([
                    ['created_by_userid', '!=', $userId],
                    ['status', '=', 'pending']
                ])
                ->count();

            if ($schoolCount !== count($vaildated['id'])) {
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
            DB::beginTransaction();
            $hardDeleted = School::whereIn('id', Arr::collapse($vaildated))->whereIn('status', ['pending', 'rejected'])->forceDelete();
            DB::commit();
            if ($hardDeleted > 0) {
                return response()->json([
                    "status" => 200,
                    "message" => "School status update successful"
                ]);
            }

            if (in_array(auth()->user()->role_id, [2, 4])) {
                return response()->json([
                    "status" => 500,
                    "message" => "The selected school id is invalid."
                ]);
            }

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
            DB::beginTransaction();

            // Start the transaction
            $school =  DB::table("schools")
                ->whereIn("id", $vaildated["id"])
                ->whereNotIn("status", [$exclude]);

            $result = $school->update($update);

            if ($reject_reason) {
                School::find($vaildated["id"][0])->reject_reason()->create([
                    'reason' => $reject_reason,
                    'created_by_userid' => auth()->user()->id
                ]);
            }

            if ($result != count($vaildated['id'])) {
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
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                "status"    => 500,
                "message"   => "School status update unsuccessful {$e->getMessage()}",
                "error"     => strval($e)
            ]);
        }
    }
}
