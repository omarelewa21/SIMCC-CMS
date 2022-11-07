<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitionMarkingGroupRequest;
use App\Models\CompetitionMarkingGroup;
use App\Models\Competition;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;


class MarkingController extends Controller
{
    public function addMarkingGroups(StoreCompetitionMarkingGroupRequest $request)
    {
        $competition = Competition::find($request->id);

        if($competition->status === "closed") {
            throw ValidationException::withMessages(['competition' => 'The selected competition is close for edit']);
        }

        DB::beginTransaction();
        try {
            $markingGroup = CompetitionMarkingGroup::create([
                'competition_id'    => $competition->id,
                'name'              => $request->name,
                'created_by_userid' => auth()->id()
            ]);

            foreach($request->countries as $country_id){
                DB::table('competition_marking_group_country')->insert([
                    'marking_group_id'  => $markingGroup->id,
                    'country_id'        => $country_id,
                    'created_at'        => now(),
                    'updated_at'        => now()
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => 500,
                "message" => "add marking group unsuccessful"
            ]);
        }

        DB::commit();
        return response()->json([
            "status" => 200,
            "message" => "add marking group successful"
        ]);
    }
}
