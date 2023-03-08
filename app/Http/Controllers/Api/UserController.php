<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitionOrganization;
use App\Models\Participants;
use App\Models\Roles;
use App\Rules\CheckBelongsToCountry;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\User;
use App\Models\Countries;
use App\Models\School;
use App\Helpers\General\CollectionHelper;
use Carbon\Carbon;

class UserController extends Controller
{
    public function create (Request $request) {

        $counter = 0;
        $currentUsername = $request[0]['username'] ?? null;

        $vaildate = array(
            '*.name' => 'required|regex:/^[\.\,\s\(\)\[\]\w-]*$/|max:255',
            '*.username' => 'required|unique:users,username|alpha_dash|min:3|max:255',
            '*.email' => 'required|unique:users,email|email|max:255',
            '*.phone' => 'required|regex:/^[0-9]*$/',
            '*.about' => 'max:65535',
            '*.password' => ['required','confirmed','min:8','regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%@]).*$/'], //            //password must English uppercase characters (A – Z), English lowercase characters (a – z), Base 10 digits (0 – 9), Non-alphanumeric (For example: !, $, #, or %), Unicode characters
        );

        if(auth()->user()->role_id == 0 || auth()->user()->role_id == 1)
        {
            switch(auth()->user()->role_id) {
                case 0:
                    $roleRules = 'required|integer|exists:roles,id';
                    break;
                case 1:
                    $roleRules = 'required|integer|exists:roles,id|min:1';
                    break;
                case 2:
                    $roleRules = 'required|integer|exists:roles,id|min:2';
                    break;
                default:
                    $roleRules ='';
            }
            $vaildate["*.role_id"] = $roleRules;
            $vaildate["*.country_id"] = 'required_if:*.role_id,2,3,4,5|integer|exists:all_countries,id|exclude_if:*.role_id,0,1';
            $vaildate["*.school_id"] = ['integer','nullable','required_if:*.role_id,3,5','exclude_if:*.role_id,0,1,2,4',new CheckBelongsToCountry(new School())];
            $vaildate['*.organization_id']  = 'required_if:*.role_id,2,3,4,5|exclude_if:*.role_id,0,1|integer|nullable';
            $vaildated = $request->validate($vaildate);

            $vaildated = collect($vaildated)->map(function ($row) { // insert organization_id for user(teacher) under parent role (country partner)
                if(isset($row['parent_id']) && $row['parent_id'] > 0) {
                    $parentOrganizationId = User::find($row['parent_id'])->organization->id;

                    $row = [
                        ...$row,
                        'organization_id' => $parentOrganizationId,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }

                return $row;
            })->toArray();

        }

        if(auth()->user()->role_id == 2) //country partner role: country_id, parent_id is already pre-set for teacher role
        {
            $vaildate["*.role_id"] = ['required','integer','exists:roles,id',Rule::in(3,4,5)]; //role 3 = teacher role, country partner allows to add teacher role
            $vaildate["*.school_id"] = ['exclude_if:*.role_id,4','required_if:*.role_id,3,5','integer',Rule::exists('schools','id')->where(function ($query) use($request,&$counter,&$currentUsername) {

                $query->where('country_id', auth()->user()->country_id)
                    ->where('status', 'active');

                if($currentUsername !== $request[$counter]['username']) {
                    $currentUsername = $request[$counter]['username'] ;
                    $counter++;
                }
            })];

            $vaildated = $request->validate($vaildate);
            $vaildated = collect($vaildated)->map(function ($row) {

                $row = [
                    ...$row,
                    'organization_id' => auth()->user()->organization_id,
                    'country_id' => auth()->user()->country_id,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                return $row;

            })->toArray();
        }

        if(auth()->user()->role_id == 4) //country partner assistant role, can create school manager and teacher
        {
            $vaildate["*.role_id"] = ['required','integer','exists:roles,id',Rule::in([3,5])]; //role 3 = teacher role, country partner allows to add teacher role
            $vaildate["*.school_id"] = ['required','integer',Rule::exists('schools','id')->where(function ($query) use($request,&$counter,&$currentUsername) {

                $query->where('country_id', auth()->user()->country_id)
                    ->where('status', 'active');

                if($currentUsername !== $request[$counter]['username']) {
                    $currentUsername = $request[$counter]['username'] ;
                    $counter++;
                }
            })];
            $vaildated = $request->validate($vaildate);
            $vaildated = collect($vaildated)->map(function ($row) {

                $row = [
                    ...$row,
                    'organization_id' => auth()->user()->organization_id,
                    'country_id' => auth()->user()->country_id,
                    'school_id' => $row['school_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];

                return $row;

            })->toArray();
        }

        if(auth()->user()->role_id == 5) //country partner assistant role, can create school manager and teacher
        {
            $vaildate["*.role_id"] = ['required','integer','exists:roles,id',Rule::in([3])]; //role 3 = teacher role, country partner allows to add teacher role
            $vaildated = $request->validate($vaildate);
            $vaildated = collect($vaildated)->map(function ($row) {

                $row = [
                    ...$row,
                    'organization_id' => auth()->user()->organization_id,
                    'country_id' => auth()->user()->country_id,
                    'school_id' => auth()->user()->school_id,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                return $row;

            })->toArray();
        }


        try {

            DB::beginTransaction();

            $foundPartnerRole = collect($vaildated)->contains(function ($item) {
                return $item['role_id'] == 2;
            });

            if($foundPartnerRole) { // create private school entry belongs to partner

                $insertSchool = collect($vaildated)->map(function ($row){
                    $OrganizationSchoolFound = School::where(['name' => 'Organization School','organization_id' => $row['organization_id'],'country_id' => $row['country_id'],'Province' => Null])->count();

                    if($OrganizationSchoolFound === 0) {
                        $row = [
                            'organization_id' => $row['organization_id'],
                            'country_id' =>  $row['country_id'],
                            'role_id' => $row['role_id'],
                            'name' => 'Organization School',
                            'private' => 1,
                            'created_by_userid' => auth()->user()->id,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return $row;
                    }
                })->filter()->all();

                for($i=0;$i<count($insertSchool);$i++) {
                    if($insertSchool[$i]['role_id'] === 2) {
                        unset($insertSchool[$i]['role_id'],);

                        School::insert($insertSchool[$i]);

                        $vaildated[$i]['school_id'] = DB::table('schools') // add school id to the insert user array
                        ->latest('id')
                            ->first()
                            ->id;
                    }
                }
            }

            $insertUsers = collect($vaildated)->map(function ($row,$index){
                $row['password'] =  Hash::make($row['password']);//add hash to password
                $row['created_by_userid'] = auth()->user()->id;
                return $row;
            })->all();

            foreach($insertUsers as $insertUser) {
                User::insert($insertUser);
            }

            DB::commit();

            return response()->json([
                "status" => 201,
                "message" => "User create successful"
            ]);

        }
        catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Create user unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function list (Request $request) {
        try {
            $vaildate = $request->validate([
                'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
                'organization_id' => 'integer',
                'limits' => 'integer',
                'page' => 'integer',
                'search' => 'max:255'
            ]);

//            $limits = $request->limits ? $request->limits : 10;
            if($request->limits == "0") {
                $limits = 99999999;
            } else {
                $limits = $request->limits ?? 10; //set default to 10 rows per page
            }
            $searchKey = isset($vaildate['search']) ? $vaildate['search'] : null;

            $countries = Countries::all()->keyBy('id')->toArray();
            $userModel = User::AcceptRequest(['status','organization_id', 'country_id', 'role_id', 'school_id', 'username', 'email']);

            if (in_array(auth()->user()->role_id,[2,4,5])) { //if role is country partner & country partner assistant, filter by their user respective account

                switch(auth()->user()->role_id) {
                    case 2:
                        $role_id = [3,4,5];
                        break;
                    case 4:
                        $role_id = [3,5];
                        break;
                    case 5:
                        $role_id = [3];
                        break;
                }

                $userModel->where(["organization_id" => auth()->user()->organization_id, "country_id" => auth()->user()->country_id])->whereIn("role_id" , $role_id)
                    ->where('status', '!=', 'deleted');//view users under this organization
            }


            $userCollection =  $userModel->filter()->get();

            /**
             * Lists of availabe filters
             */
            $temp = '';
            $availUserStatus = $userCollection->map(function ($item) {
                return $item['status'];
            })->unique()->values();
            $availCountry = $userCollection->map(function ($item) {
                return ["id" => $item['country_id'], "country" => $item['country_name']];
            })->unique()->values();
            $availRole = $userCollection->map(function ($item) {
                return ['id' => $item['role_id'], 'name' => $item['role_name']];
            })->unique()->values();
            $availSchool = $userCollection->map(function ($item) {
                return ['id' => $item['school_id'], 'name' => $item['school_name']];
            })->unique()->values();
            $availOrganization = $userCollection->map(function ($item) {
                return ['id' => $item['organization_id'], 'name' => $item['organization_name']];
            })->filter(function ($item) {
                return isset($item['id']);
            })->unique()->values();

            /**
             * EOL Lists of availabe filters
             */

            $availForSearch = array("name", "username", "email", "organization_name");
            $userList = CollectionHelper::searchCollection($searchKey, $userCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['status' => $availUserStatus, 'organization' => $availOrganization, 'countries' => $availCountry, 'schools' => $availSchool, 'role' => $availRole], "userLists" => $userList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        }
        catch(QueryException $e) {
            return response()->json([
                "status" => 500,
                "message" => "Retrieve users retrieve unsuccessful" ,
            ]);
        }
        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve users retrieve unsuccessful"
            ]);
        }
//        catch (\Exception $e) {
//            return response()->json([
//                "status" => 500,
//                "message" => "Retrieve users retrieve unsuccessful"
//            ]);
//        }
    }

    public function login (Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);
        try {
            $user = User::where("username", $request->username)
                ->orWhere('email', $request->username)
                ->with('school', 'organization')
                ->firstOrFail();

            if ($user->status == 'active') {

                if (Hash::check($request->password, $user->password)) {

                    //create a token
                    $token = $user->createToken("auth_token")->plainTextToken;
                    $user->last_login = Carbon::now();
                    $user->save();



                    return response()->json([
                        "status"        => 200,
                        "message"       => "Login successful",
                        "role_id"       => $user->role_id,
                        "user_id"       => $user->id,
                        "country_id"    => $user->country_id,
                        "school_id"     => $user->school_id,
                        "school"        => $user->school,
                        "organization"  => $user->organization,
                        "private"       => $user->private_school,
                        "parent_id"     => $user->parent_id,
                        "token"         => $token
                    ]);

                } else {
                    return response()->json([
                        "status" => 401,
                        "message" => "Incorrect password or username"
                    ], 401);
                }
            } else {
                return response()->json([
                    "status"  => 404 ,
                    "message" => "User not found"
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 404 ,
                "message"   => "User not found"
            ], 404);
        }
    }

    public function logout () {

        auth()->user()->tokens()->delete();

        return response()->json([
            'status' => 200,
            "message" => "User logout successful"
        ]);
    }

    public function profile () {
        return response()->json([
            "status" => 200,
            "data" => User::find(auth()->user()->id)
        ]);
    }

    public function update (Request $request) {

        //password must English uppercase characters (A – Z), English lowercase characters (a – z), Base 10 digits (0 – 9), Non-alphanumeric (For example: !, $, #, or %), Unicode characters
        $request['role_id'] = auth()->user()->role_id;

        $vaildate = array(
//            'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/|min:3|max:255',
            'role_id' => 'integer|exists:roles,id',
            'about' => 'max:65535',
            'email' => ['email','max:255',Rule::unique('users')->ignore(auth()->user()->email, 'email')],
            'phone' => 'regex:/^[0-9]*$/',
            'password' => ['confirmed','min:8','regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%@]).*$/'],
            'organization_id' => 'integer|exists:organization,id|exclude_if:role_id,0,1',
            'country_id' => 'integer|exists:all_countries,id'
        );

        if(auth()->user()->role_id == 0) {
            $vaildate["id"] = ["required","exists:users,id","integer"];
        }

        if(auth()->user()->role_id == 1) { //admin can update anyone except admin
            $vaildate["id"] = ["required",Rule::exists('users','id')->whereIn('role_id',[2,3,4,5] ),"integer"];
        }

        if(auth()->user()->role_id == 2) { // country partner can update user below them within the same organization
            $vaildate["id"] = ["required",Rule::exists('users','id')->where('organization_id', auth()->user()->organization_id)->where('country_id' , auth()->user()->country_id)->whereIn('role_id',[3,4,5]),"integer"];
        }

        if(auth()->user()->role_id == 4) { // country partner can update user below them within the same organization
            $vaildate["id"] = ["required",Rule::exists('users','id')->where('organization_id', auth()->user()->organization_id)->where('country_id' , auth()->user()->country_id)->whereIn('role_id',[3,5]),"integer"];
        }

        if(auth()->user()->role_id == 5) { // country partner can update user below them within the same organization
            $vaildate["id"] = ["required",Rule::exists('users','id')->where('organization_id', auth()->user()->organization_id)->where('country_id' , auth()->user()->country_id)->whereIn('role_id',[3]),"integer"];
        }

        if($request->id == null) unset($vaildate['id']);

        $validated = $request->validate($vaildate);

        try{
            $changeOrg = false;
            $user = User::with(['competitionOrganization.competition' => function ($query) {
                $query->where('competition.status','active');
            }])->get();

            $userid = (isset($validated['id']) ? $validated['id'] : auth()->user()->id); // if update profile use own id

            if($user->where('id',$userid)->first()->role_id == 2) {
//                $userSchoolId = $user->where('id',$userid)->first()->school_id;
//                $createdUserCount = $user->where('created_by_userid',$userid)->count();
//                $modifiedUserCount = $user->where('last_modified_userid',$userid)->count();
//                $createdSchoolCount = School::where('id','!=',$userSchoolId)->where('created_by_userid',$userid)->count();
//                $modifiedSchoolCount = School::where('id','!=',$userSchoolId)->where('last_modified_userid',$userid)->count();
//                $partnerParticipantCount = Participants::where(['created_by_userid' => $userid])->count();
//                $modifiedParticipantCount = Participants::where('last_modified_userid',$userid)->count();

//                $changeOrg = $createdUserCount == 0 && $modifiedUserCount == 0 && $createdSchoolCount == 0 && $modifiedSchoolCount == 0 && $partnerParticipantCount == 0 && $modifiedParticipantCount == 0? true : false;
                  $changeOrg = True;
            }

            DB::beginTransaction();

            $user = User::findOrFail($userid);

            $organization_id = $request->organization_id ?? $user->organization_id;
            $country_id = $request->country_id ?? $user->country_id;

            if($changeOrg) {
                $user->organization_id = $organization_id;
                $user->country_id = $country_id;

            } elseif (isset($request->organization_id) && isset($changeOrg) && !$changeOrg) {
                abort(404,'Changing of organization is not allowed');
            }

            if(isset($request->name)) {
                $user->name = $request->name;
            }

            if(isset($request->username)) {
                $user->username = $request->username;
            }

            if(isset($request->email)) {
                $user->email = $request->email;
            }

            if(isset($request->phone)) {
                $user->phone = $request->phone;
            }

            if(isset($request->about)) {
                $user->about = $request->about;
            }

            if(!empty($request->password))
            {
                $user->password = Hash::make($request->password);
            }

            $user->last_modified_userid = auth()->user()->id;

            $user->save();

            $OrganizationSchoolFound =  $user->role_id != 0 &&  $user->role_id != 1? School::where(['name' => 'Organization School','organization_id' => $user->organization_id,'country_id' => $user->country_id, 'Province' => NULL])->count() : null;

            if($OrganizationSchoolFound === 0) {
                $row = [
                    'organization_id' => $user->organization_id,
                    'country_id' =>  $user->country_id,
                    'name' => 'Organization School',
                    'private' => 1,
                    'created_by_userid' => auth()->user()->id,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                School::create($row);

            }
            DB::commit();

            return response()->json([
                "status" => 200 ,
                "message" => "Users update successful"
            ]);
        }
        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Users update unsuccessful"
            ]);
        }

//        catch (\Exception $e) {
//            return response()->json([
//                "status" => 500,
//                "message" => "Users update unsuccessful"
//            ]);
//        }

    }

    public function disable (Request $request) {
        return $this->_updateStatus($request,"disabled", "disabled,deleted");
    }

    public function undisable (Request $request) {
        return $this->_updateStatus($request,"active", "active,deleted");
    }

    public function delete (Request $request) {
        if($request->delete == 1) {

            $validated = $request->validate([
                'id' => 'array',
                'id.*' => 'integer'
            ]);

            try {
                $result = User::destroy($validated['id']);

                if ($result) {
                    return response()->json([
                        "status" => 200,
                        "message" => $result . " user delete successful"
                    ]);
                }

            } catch (ModelNotFoundException $e) {
                // do task when error
                return response()->json([
                    "status" => 500,
                    "message" => "delete user unsuccessful"
                ]);
            }
        }

        return $this->_updateStatus($request,"deleted", "deleted");
    }

    private function _updateStatus ($request, $status, $exclude) {

        try {
            // {"id" : [xx,xx,xx]}
            $validated = $request->validate([
                "id" => "array",
                "id.*" => "required|integer",
            ]);

            if(Route::currentRouteName() == "user.disable")
            {
                $update = array(
                    "status" => "disabled",
                );
            }

            if(Route::currentRouteName() == "user.undisable")
            {
                $update = array(
                    "status" => "active",
                );
            }

            if(Route::currentRouteName() == "user.delete")
            {
                $update = array(
                    "status" => "deleted",
                );
            }

            $update["last_modified_userid"] = auth()->user()->id;

            DB::beginTransaction();

            // Start the transaction
            $users =  DB::table("users")
                ->whereIn("id", $validated["id"])
                ->whereNotIn ("status",[$exclude]);

            if(auth()->user()->role_id == 2) {
                $users->where(["organization_id" => auth()->user()->role_id, 'country_id' => auth()->user()->country_id])
                    ->whereIn('role_id', [3,4,5]);
            }

            if(auth()->user()->role_id == 4) {
                $users->where(["organization_id" => auth()->user()->role_id, 'country_id' => auth()->user()->country_id])
                    ->whereIn('role_id', [3,5]);
            }

            $result = $users->update($update);

            if($result != count($request->id))
            {
                DB::rollback();

                return response()->json([
                    "status" => 500,
                    "message" => "$status unsuccessful"
                ]);
            }

            DB::commit();


            if(isset($notificationBody)) {  //send notification to the user that create the school entry
                $info = $users->get(['created_by_userid','name']);

                foreach($info as $row) {
                    $user = User::find($row->created_by_userid);
                    $userName = $row->name;

                    $data = [
                        'body' =>  [
                            "page" => "school",
                            "message" => "Pending - $userName $notificationBody",
                            "status" => 200,
                        ]
                    ];

                }
            }

            return response()->json([
                "status" => 200,
                "message" => "User status update successful"
            ]);

        }
        catch(QueryException $e) {
            DB::rollback();

            return response()->json([
                "status" => 500,
                "message" => "User status update unsuccessful"
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Users update unsuccessful"
            ]);
        }
    }

    protected function CheckUserLinkRecords ($id,$role_id,$checkArray) {
        $user = User::findOrFail($id);
        $role_id = Roles::findOrFail($role_id);

        if(in_array('user',$checkArray)) {
            $createdUserCount = $user->where('created_by_userid',$id)->count();
            $modifiedUserCount = $user->where('last_modified_userid',$id)->count();
        }

        if(in_array('school',$checkArray)) {
            $school = new School();
            switch($role_id) {
                case 2:
                    $userSchoolId = $user->school_id;
                    break;
                case 4:
                    $userSchoolId = $user->parent()->first()->school_id;
                    break;
            }
            $school->where('id','!=',$userSchoolId);
            $createdSchoolCount = School::where('created_by_userid',$id)->count();
            $modifiedSchoolCount = School::where('last_modified_userid',$id)->count();
        }

        if(in_array('participant',$checkArray)) {

            switch($role_id) {
                case 2:
                    $createParticipantCount = Participant::where('created_by_userid',$id)->count();
                    $modifiedParticipantCount = Participant::where('last_modified_userid',$id)->count();

            }
            $createParticipantCount = Participant::where('created_by_userid',$id)->count();
            $modifiedParticipantCount = Participant::where('last_modified_userid',$id)->count();
        }

        $changeOrg = $createdUserCount == 0 && $modifiedUserCount == 0 && $createdSchoolCount == 0 && $modifiedSchoolCount == 0 ? true : false;


    }

}
