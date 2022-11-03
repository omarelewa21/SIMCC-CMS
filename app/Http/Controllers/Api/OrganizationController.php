<?php

namespace App\Http\Controllers\api;

use App\Helpers\General\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Models\Countries;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    protected $countries;

    public function __construct()
    {
        $this->countries = Countries::get()->pluck('id');
    }

    public function list (Request $request) {

        $vaildate = $request->validate([
            'id' => "integer",
            'name' => 'regex:/^[\.\,\s\(\)\[\]\w-]*$/',
            'status' => 'alpha',
            'country_id' => 'integer',
            'limits' => 'integer',
            'page' => 'integer',
            'search' => 'max:255'
        ]);

        try {

            $limits = $request->limits ? $request->limits : 10; //set default to 10 rows per page
            $searchKey = isset($vaildate['search']) ? $vaildate['search'] : null;
            $countries = Countries::all()->keyBy('id')->toArray();
            $eagerload = [
                'users'
            ];

            $organizationModel = Organization::with($eagerload)
                ->AcceptRequest(['status', 'country_id', 'name', 'id']);

            $returnFiltered = $organizationModel
                ->filter()
                ->get();

            $organizationCollection = collect($returnFiltered)->map(function ($item) use($countries){
                $item['country'] = $countries[$item['country_id']]['display_name'];
                return $item;
            });

            /**
             * Lists of availabe filters
             */


            $availCountry = $organizationCollection->map(function ($item) use (&$countries) {
                return ["id" => $item['country_id'], "name" => $countries[$item['country_id']]['display_name']];
            })->unique()->values();

            /**
             * EOL Lists of availabe filters
             */

            $availForSearch = array("name", "email", "address", "person_incharge", "phone");
            $organizationList = CollectionHelper::searchCollection($searchKey, $organizationCollection, $availForSearch, $limits);
            $data = array("filterOptions" => ['countries' => $availCountry], "OrganizationLists" => $organizationList);

            return response()->json([
                "status" => 200,
                "data" => $data
            ]);
        }

        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Retrieve organization unsuccessful"
            ]);
        }

    }

    public function create (Request $request) {

        $counter = 0;

        $validated = $request->validate([
            "*.name" => ["required","distinct","regex:/^[\&\(\)\:\'\\\"\;\.\,\s\(\)\[\]\w-]*$/",Rule::unique("organization")->where(function ($query) use(&$counter,$request) {
                $query->where('name', $request[$counter.'.name'])
                    ->where('country_id',$request[$counter.'.country_id']); //make sure only 1 unique organization name per country
                $counter++;
            })],
            "*.person_incharge" => "regex:/^[\'\;\.\,\s\(\)\[\]\w-]*$/",
            "*.country_id" => ['required','integer',Rule::in($this->countries)],
            "*.address" => "required|max:255",
            "*.billing_address" => "max:255",
            "*.email" => "required|email",
            "*.phone" => "required|array",
            "*.phone.*" => "required|regex:/^[0-9]*$/",
            "*.logo" => "max:1000000"
        ]);

        for($i=0;$i<count($validated);$i++) {
            $validated[$i] = [
                ...$validated[$i],
                "country_id" => $validated[$i]['country_id'],
                "created_by_userid" => auth()->user()->id,
                "created_at" => date('Y-m-d H:i:s'),
                "phone" => json_encode($validated[$i]['phone']),

            ];
        }

        try{
            Organization::insert($validated);

            return response()->json([
                "status" => 201,
                "message" => "Organization create successful"
            ]);

        }
        catch (\Exception $e) {

            return response()->json([
                "status" => 500,
                "message" => "Create Organization unsuccessful"
            ]);

        }
    }

    public function update (Request $request) {

        $validated = $request->validate([
            "id" => "required|integer|exists:organization,id",
            "name" => ["sometimes","required","distinct","regex:/^[\&\(\)\:\'\\\"\;\.\,\s\(\)\[\]\w-]*$/",Rule::unique("organization")->where(function ($query) use($request) {
                $query->where('name', $request['name'])
                    ->where('country_id',$request['country_id']); //make sure only 1 unique organization name per country
            })],
            "person_incharge" => "regex:/^[\'\;\.\,\s\(\)\[\]\w-]*$/",
            "country_id" => ['required','integer',Rule::in($this->countries)],
            "address" => "required|max:255",
            "billing_address" => "max:255",
            "email" => "required|email",
            "phone" => "required|array",
            "phone.*" => "required|regex:/^[0-9]*$/",
            "logo" => "max:1000000"
        ]);

        $validated = [
            ...$validated,
            "country_id" => $validated['country_id'],
            "modified_by_userid" => auth()->user()->id,
            "updated_at" => date('Y-m-d H:i:s'),
            "phone" => json_encode($validated['phone']),
        ];

        $organization_id = Arr::pull($validated,'id');

        try{
            $organization = Organization::findOrFail($organization_id);
            $organization->fill($validated);
            $organization->save($validated);

            return response()->json([
                "status" => 200,
                "message" => "Organization update successful"
            ]);

        }
        catch (\Exception $e) {

            return response()->json([
                "status" => 500,
                "message" => "Organization update unsuccessful"
            ]);

        }

        catch(\Exception $e){
            return response()->json([
                "status" => 500,
                "message" => "Organization update unsuccessful"
            ]);

        }
    }

    public function delete (Request $request) {
        $validated = $request->validate([
            "id" => "required|array",
            "id.*" => "required|integer|distinct|exists:organization,id"
        ]);

        try {

            DB::beginTransaction();

            foreach ($validated['id'] as $id) {

                $organization = Organization::with(['users'])->where('id',$id)->first();
                $userCount = $organization->users()->count();

                if ($userCount > 0) {
                    $organization->status = 'deleted';
                    $organization->save();
                } else {
                    $organization->forceDelete();
                }

            }

            DB::commit();

            return response()->json([
                "status" => 200,
                "message" => "Organization delete successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => 500,
                "message" => "Organization delete unsuccessful"
            ]);
        }


    }
}
