<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParticipantResource;
use App\Models\Competition;
use App\Models\Countries;
use App\Models\Languages;
use App\Models\Participants;
use App\Models\Roles;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class HelperController extends Controller
{

    public function getCountryList (Request $request) {
        $request->validate([
            "competition_id" => ['integer',Rule::exists("competition",'id')]
        ]);

        if($request->competition_id) {
            $countryIds = Countries::getCompetitionCountryList(Competition::find($request->competition_id));
            $list = Countries::whereIn('id',$countryIds)->get(['id','Dial','display_name','ISO3166-1-Alpha-2']);

        } else {
            $list = Countries::all(['id','Dial','display_name','ISO3166-1-Alpha-2']);
        }

        return response()->json([
            "status" => 200,
            "data" => $list
        ]);
    }

    public function getLanguagesList () {
        $list = Languages::all(['id','name']);

        return response()->json([
            "status" => 200,
            "data" => $list
        ]);
    }

    public function levelCountyParticipantsList (Request $request) {

        $countries = Countries::all()->mapWithKeys(function ($row) { return [$row['id'] => $row['display_name']]; })->toArray();

//        dd($countries->toArray());

        $competition_id = implode("",$request->validate([
            "competition_id" => ['required','integer',Rule::exists("competition",'id')]
        ]));


        $competition = Competition::with('competitionOrganization:id,competition_id','rounds.levels.groups')->find($competition_id);

        $competitionOrganization_id = $competition->competitionOrganization->pluck('id')->toArray();

        $rounds = $competition->rounds->map(function ($round) use($competitionOrganization_id,$countries){

            $levels = $round['levels']->map(function ($level) use($competitionOrganization_id,$countries) {

                $country = Participants::whereIn('competition_organization_id',$competitionOrganization_id)->whereIn('grade',$level['grades'])->get()->mapWithKeys(function ($participant) use($countries) {
                    return
                        [
                            $participant['country_id'] => $countries[$participant['country_id']]
                        ];
                });

                return [
                    'level_id' => $level['id'],
                    'level_name' => $level['name'],
                    'Countries' => $country
                ];
            });

            return [
            'round_id' => $round['id'],
                'name' => $round['name'],
                'levels' => $levels
            ];
        });

        return response()->json(
            [
                'status' => 200,
                'data' => $rounds
            ]
        );
    }

    public function getRoleList () {

        $roles = new Roles;

        switch(auth()->user()->role_id ) {
            case 0:
                $roles = $roles->whereIn("id",[0,1,2,3,4,5]);
                break;
            case 1:
                $roles = $roles->whereIn("id",[2,3,4,5]);
                break;
            case 2:
                $roles = $roles->whereIn("id",[3,4,5]);
                break;
            case 4:
                $roles = $roles->whereIn("id",[3,5]);
                break;
            case 5:
                $roles = $roles->whereIn("id",[3]);
                break;
        }

        $roles = $roles->get(['id','name']);

        return response()->json([
            "status" => 200,
            "data" => $roles
        ]);
    }



//    public function getCompetitionList () {
//
//        $competitions = new Competition();
//
//        switch(auth()->user()->role_id) {
//            case 0:
//            case 1:
//                $competitions = $competitions->with(['rounds'])->where('status','active')->get();
//                break;
//            case 2:
//                $competitions = $competitions->with(['partnerDate:partner_userid,competition_id','rounds'])->where('status','active')
//                    ->whereHas('partnerDate', function ($row) {
//                        $row->where('partner_userid', auth()->user()->id);
//                    })->get();
//                break;
//            default :
//                return response()->json([
//                    "status" => 500,
//                    "message" => 'Role is not allowed to access!'
//                ]);
//        }
//
//        return response()->json([
//            "status" => 200,
//            "data" => $competitions
//        ]);
//    }

    // convert db structure, remove after complete changes
    public function ConvertCompetitionOrganizationTable () {
        $temp = CompetitionPartner::with('partner')->get(['id','partner_userid'])->map( function ($item) {
            $temp = CompetitionPartner::find($item->id);
            $temp->organization_id = $item['partner'][0]->organization_id;
            $temp->country_id = $item['partner'][0]->country_id;
            $temp->save();
        });

        return $temp;
    }

    public function CreateSchoolForOrganization () {

        DB::beginTransaction();

        $deletepartnerschool = School::where('name','LIKE','% - School')->get();
        $partnerschoolIds = $deletepartnerschool->pluck('id')->toArray();

        User::with('organization')->where(['role_id' => 2])->groupBy('organization_id','country_id')->get()->map(function ($row) use(&$createdSchool) {
            return $createdSchool[] = School::create([
                'organization_id' => $row['organization_id'],
                'country_id' => $row['country_id'],
                'name' => 'Organization School',
                'status' => 'active',
                'private' => 1,
                'created_by_userid' => auth()->user()->id,
                'approved_by_userid' => auth()->user()->id,
                'created_at' => date('Y-m-d', strtotime("now"))
            ])->toArray();
        });

        $createdSchool = collect($createdSchool)->mapWithKeys(function ($item,$key) {
            return [$item['organization_id'] => $item['id']];
        })->toArray();

        $participants = Participants::with('competition_organization')->whereIn('tuition_centre_id' ,$partnerschoolIds)->get()->map(function ($item) use($createdSchool) {
            $organization_id = $item['competition_organization']['organization_id'];
            $participant = Participants::where('id',$item->id)->first();
            $participant->tuition_centre_id = $createdSchool[$organization_id];
            $participant->save();
            return $participant;
        });

        User::where('role_id',2)->update(['school_id' => null]);

//        collect($partnerschoolIds)->map(function ($row) {
//            School::find($row)->delete();
//        });

        School::whereIn('id', $partnerschoolIds)->delete();

        DB::commit();

        return 'success';

    }

    public function setIndexCounterCountryTable () {
        $non_private = Participants::whereNull('tuition_centre_id')->orderBy('id','desc')->get()->unique('country_id')->mapWithKeys(function ($item) {
            return [$item['country_id'] => $item['index_no']];
        })->toArray();
        $private = Participants::whereNotNull('tuition_centre_id')->orderBy('id','desc')->get()->unique('country_id')->mapWithKeys(function ($item) {
            return [$item['country_id'] => $item['index_no']];
        })->toArray();

        collect($non_private)->map(function ($row,$key) {
            $save = Countries::find($key);
            $save->participant_counter = substr($row, 3);
            $save->save();
        });

        collect($private)->map(function ($row,$key) {
            $save = Countries::find($key);
            $save->private_participant_counter = substr($row, 3);
            $save->save();
        });
    }

    public function GenerateCertNum () {
        try {
            Participants::where('status', 'active')->get()->each(function ($row, $index) {
                $certNo = 'A' . str_pad($row->id, 7, '0', STR_PAD_LEFT);
                $temp = Participants::find($row->id);
                $temp->certificate_no = $certNo;
                $temp->save();
            });

            return 'done';
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function getParticipantInfo(Participants $participant) {
        try {
            $participant->markAnswers();

            $data = $participant->load('school:id,name','country:id,display_name as name', 'answers')
                ->loadCount('answers')
                ->toArray();
            $data['school'] = $data['school']['name'];
            $data['country'] = $data['country']['name'];
            $answers = collect($data['answers'])->sortBy('id')->map(function ($answer, $key) {
                $answerKey = $answer['answer'];
                if(!is_null($answer['is_correct'])) {
                    $answerKey .= $answer['is_correct'] ? ' (Correct)' : ' (Wrong)';
                }
                return [
                    "Q" . $key+1 => $answerKey,
                ];
            })->collapse();

            $data = array_merge($data, $answers->toArray());
            unset($data['answers']);


            $headers = [
                'Index'     => 'index_no',
                'Name'      => 'name',
                'Country'   => 'country',
                'School'    => 'school',
                'Grade'     => 'grade',
                'status'    => 'status',
                'No. of answers uploaded' => 'answers_count',
            ];

            for($i = 1; $i <= $answers->count(); $i++) {
                $headers["Q$i"] = "Q$i";
            }

            return response()->json([
                'status' => 200,
                'headers' => $headers,
                'data'   => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => $e->getMessage(),
                'error'     => strval($e)
            ]);
        }


    }
}
