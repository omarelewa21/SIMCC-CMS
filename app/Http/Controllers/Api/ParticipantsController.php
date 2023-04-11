<?php

namespace App\Http\Controllers\api;

use App\Custom\ParticipantReportService;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use \Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\Participants;
use App\Models\Countries;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\DeleteParticipantRequest;
use App\Http\Requests\getParticipantListRequest;
use App\Http\Requests\ParticipantReportWithCertificateRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Models\CompetitionParticipantsResults;
use App\Rules\CheckSchoolStatus;
use App\Rules\CheckCompetitionAvailGrades;
use App\Rules\CheckParticipantRegistrationOpen;
use App\Rules\CheckOrganizationCompetitionValid;
use App\Rules\CheckCompetitionEnded;
use Exception;
use PDF;

class ParticipantsController extends Controller
{
    public function create (Request $request) {

        $request['role_id'] = auth()->user()->role_id;

        Countries::all()->map(function ($row) use(&$ccode) {
            $ccode[$row->id] = $row->Dial;
        });

        $validate = array(
            "role_id" => "nullable",
            "participant.*.competition_id" => ["required","integer","exists:competition,id", new CheckOrganizationCompetitionValid, new CheckCompetitionEnded('create'), new CheckParticipantRegistrationOpen],
            "participant.*.country_id" => 'exclude_if:role_id,2,3,4,5|required_if:role_id,0,1|integer|exists:all_countries,id',
            "participant.*.organization_id" => 'exclude_if:role_id,2,3,4,5|required_if:role_id,0,1|integer|exists:organization,id',
            "participant.*.name" => "required|string|max:255",
            "participant.*.class" => "required|max:255|nullable",
            "participant.*.grade" => ["required","integer",new CheckCompetitionAvailGrades],
            "participant.*.for_partner" => "required|boolean",
            "participant.*.partner_userid" => "exclude_if:*.for_partner,0|required_if:*.for_partner,1|integer|exists:users,id",
            "participant.*.tuition_centre_id" => ['exclude_if:*.for_partner,1','required_if:*.school_id,null','integer','nullable',new CheckSchoolStatus(1)],
            "participant.*.school_id" => ['exclude_if:role_id,3,5','required_if:*.tuition_centre_id,null','nullable','integer',new CheckSchoolStatus],
            "participant.*.email"     => ['sometimes', 'email','nullable']
            // "participant.*.email"     => ['sometimes', 'email', new ParticipantEmailRule]
        );

        $validated = $request->validate($validate);
        $validated = data_fill($validated,'participant.*.class',null); // add missing class attribute and set to null

        try {
            DB::beginTransaction();

            $returnData = [];
            $indexNoList = [];
            $validated = collect($validated['participant'])->map(function ($row,$index) use($validated,$ccode,&$indexNoList,&$returnData) {

                switch(auth()->user()->role_id) {
                    case 0:
                    case 1:
                        $organizationId = $row["organization_id"];
                        $countryId = $row["country_id"];
                    case 2:
                    case 4:
                        $organizationId = $organizationId ?? auth()->user()->organization_id;
                        $countryId = $countryId ?? auth()->user()->country_id;
                        $schoolId =  $row["school_id"];
                        $tuitionCentreId = $row["tuition_centre_id"];
                        break;
                    case 3:
                    case 5:
                        $organizationId = auth()->user()->organization_id;
                        $schoolId =  auth()->user()->school_id;
                        break;
                }

                if(isset($tuitionCentreId)) {
                    $row["tuition_centre_id"] = $tuitionCentreId;
                    $row["school_id"] = $schoolId;
                }
                else
                {
                    $row["school_id"] = $schoolId;
                }

                if(isset($row["for_partner"]) && $row["for_partner"] == 1)  {
                    $row["tuition_centre_id"] = School::where(['name' => 'Organization School','organization_id' => $organizationId,'country_id' => $countryId, 'province' => null])
                        ->get()
                        ->pluck('id')
                        ->firstOrFail();
                }

                $country_id  = in_array(auth()->user()->role_id , [2,3,4,5])? auth()->user()->country_id : $row["country_id"];
                $CountryCode = $ccode[$country_id];
                $private = isset($tuitionCentreId) ? 1 : 0;
                $temp = str_pad($CountryCode,3,"0",STR_PAD_LEFT).substr( date("y"), -2). $private;

                /*Generate index no.*/
                //$country = Countries::find($country_id);
                $country = Countries::where(['dial'=>$CountryCode,'update_counter'=>1])->first();
                $currentYear = substr( date("y"), -2);
                $indexNo = $private ? $currentYear . '1000001' : $currentYear . '0000001';
                

                if($country->private_participant_counter && $country->private_participant_counter >= $country->participant_counter) {
                    $counter = $country->private_participant_counter;

                    if($counter){
                        $counterYear = substr($counter,0,2);
                        if(intval($currentYear) > intval($counterYear)) { // new year reset index counter
                            $country->private_participant_counter = $indexNo; // get the YY|Private/non-Private|Counter
                        }
                        else
                        {
                            $indexNo = strval(intval($counter) + 1);
                            $country->private_participant_counter = $indexNo;
                        }
                    }
                    else
                    {
                        $country->private_participant_counter = $indexNo;
                    }
                }
                else
                {
                    $counter = $country->participant_counter;

                    if($counter){
                        $counterYear = substr($counter,0,2);

                        if(intval($currentYear) > intval($counterYear)) {
                            $country->participant_counter = $indexNo;
                        }
                        else
                        {
                            $indexNo = strval(intval($counter) + 1);
                            $country->participant_counter = $indexNo;
                        }
                    }
                    else
                    {
                        $country->participant_counter = $indexNo;
                    }
                }

                $country->save();

                if(!isset($indexNoList[$temp])) {
                    $indexNoList[$temp] = [];
                }

                if(!in_array($indexNo,$indexNoList[$temp])) {
                    $indexNoList[$temp][] = $indexNo;
                } else {
                    $indexNo = last($indexNoList[$temp]) + 1;
                    $indexNoList[$temp][] = $indexNo;
                }

                //Generate Certificate No.
                $latestIndex = Participants::orderBy('id','desc')->first()->id + 19522;

                $toNextLetterCounter = floor($latestIndex / 1000000);
                $startLetter = function () use($toNextLetterCounter) {
                    $letter = 'A';
                    if($toNextLetterCounter > 0){
                        for ($i=0;$i < $toNextLetterCounter;$i++){
                            $letter++;
                        }
                    };
                    return $letter;
                };
                $certificateNumber = $latestIndex > 1000000 ? $startLetter() . str_pad((($latestIndex % 10000000) + $index),7,"0",STR_PAD_RIGHT) : $startLetter() . str_pad((($latestIndex % 10000000) + $index),7,"0",STR_PAD_LEFT);

                $row['competition_organization_id'] = CompetitionOrganization::where(['competition_id' => $row['competition_id'], 'organization_id' => $organizationId])->firstOrFail()->id;
                $row['session'] = Competition::findOrFail($row['competition_id'])->competition_mode == 0 ? 0 : null;
                $row["country_id"] = $country_id;
                $row["created_by_userid"] =  auth()->user()->id; //assign entry creator user id
                $row["index_no"] = str_pad($CountryCode.$indexNo,12,"0",STR_PAD_LEFT);
                $row["certificate_no"] = $certificateNumber;
                $row["passkey"] = Str::random(8);
                $row["password"] = Hash::make($row["passkey"]);
                $row["created_at"] = date('Y-m-d H:i:s');

                $returnData[] = $row;
                unset($returnData[count($returnData)-1]['password']);
                unset($row['passkey']);
                unset($row['competition_id']);
                unset($row['for_partner']);
                unset($row['organization_id']);

                return $row;
            })->toArray();

            Participants::insert($validated);;

            DB::commit();

            return response()->json([
                "status" => 201,
                "message" => "create Participants successful",
                "data" => $returnData
            ]);
        }

        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Create Participants unsuccessful",
            ]);
        }
        catch(QueryException $e) {
            return response()->json([
                "status" => 500,
                "message" => "Create Participants unsuccessful" .$e,
            ]);
        }
    }

    public function list (getParticipantListRequest $request)
    {
        $participantCollection = Participants::leftJoin('users as created_user','created_user.id','=','participants.created_by_userid')
            ->leftJoin('users as modified_user','modified_user.id','=','participants.last_modified_userid')
            ->leftJoin('all_countries','all_countries.id','=','participants.country_id')
            ->leftJoin('schools','schools.id','=','participants.school_id')
            ->leftJoin('schools as tuition_centre','tuition_centre.id','=','participants.tuition_centre_id')
            ->leftJoin('competition_organization','competition_organization.id','=','participants.competition_organization_id')
            ->leftJoin('organization','organization.id','=','competition_organization.organization_id')
            ->leftJoin('competition','competition.id','=','competition_organization.competition_id')
            ->leftJoin('competition_participants_results','competition_participants_results.participant_index','=','participants.index_no')
            ->select(
                'participants.id',
                'participants.name',
                'participants.email',
                'participants.index_no',
                'participants.email',
                'participants.class',
                'participants.tuition_centre_id',
                'participants.grade',
                'participants.country_id',
                'participants.certificate_no',
                'participants.session',
                'participants.status',
                'all_countries.display_name as country_name',
                DB::raw("CASE WHEN participants.tuition_centre_id IS NULL THEN 0 ELSE 1 END AS private"),
                'schools.id as school_id',
                'schools.name as school_name',
                'tuition_centre.id as tuition_centre_id',
                'tuition_centre.name as tuition_centre_name',
                'competition.id as competition_id',
                'competition.name as competition_name',
                'competition.alias as competition_alias',
                'organization.id as organization_id',
                'organization.name as organization_name',
                'competition_participants_results.award',
                DB::raw("CONCAT_WS(' ',created_user.username,DATE_FORMAT(participants.created_at,'%d/%m/%Y')) as created_by"),
                DB::raw("CONCAT_WS(' ',modified_user.username,DATE_FORMAT(participants.updated_at,'%d/%m/%Y')) as last_modified_by")
            )
            ->filterList($request)
            ->get();
        try {
            if($request->limits == "0") {
                $limits = 99999999;
            } else {
                $limits = $request->limits ?? 10; //set default to 10 rows per page
            }

            /**
             * Lists of availabe filters
             */
            $availUserStatus = $participantCollection->map(function ($item) {
                return $item->status;
            })->unique()->values();
            $availGrade = $participantCollection->map(function ($item) {
                return $item->grade;
            })->unique()->sort()->values();
            $availPrivate = $participantCollection->map(function ($item) {
                return $item->private;
            })->unique()->values();
            $availCountry = $participantCollection->map(function ($item) {
                return ["id" => $item->country_id, "name" => $item->country_name];
            })->unique()->sortBy('name')->values();
            $availCompetition = $participantCollection->map(function ($item) {
                return ["id" => $item->competition_id, "name" => $item->competition_name];
            })->unique()->sortBy('name')->values();
            $availOrganization = $participantCollection->map(function ($item) {
                return ['id' => $item->organization_id, 'name' => $item->organization_name];
            })->unique()->sortBy('name')->values();

            /**
             * EOL Lists of availabe filters
             */

            if($request->has('competition_id')) {
                /** addition filtering done in collection**/
                $participantCollection = $this->filterCollectionList($participantCollection,[
                    "0,competition_id" => $request->competition_id ?? false, // 0 = non-nested, 1 = nested
                ],"competition_id"
                );
            }

            $availForSearch = array("name", "index_no", "school", "tuition_centre");
            $participantList = CollectionHelper::searchCollection('', $participantCollection, $availForSearch, $limits);

            return response()->json([
                "status"    => 200,
                "data"      => [
                    "filterOptions" => [
                        'status'        => $availUserStatus,
                        'organization'  => $availOrganization,
                        'grade'         => $availGrade,
                        'private'       => $availPrivate,
                        'countries'     => $availCountry,
                        'competition'   => $availCompetition
                    ],
                    "participantList" => $participantList
                ]
            ]);
        }
        catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "The filter entered doesn't return any data, please change field parameters and try again",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function update (UpdateParticipantRequest $request)
    {
        try {
            $participant = Participants::where(['id' => $request['id'], 'status' => 'active'])->firstOrFail();
            $participant->name  = $request->name;
            $participant->grade = $request->grade;
            $participant->class = $request->class;
            $participant->email = $request->email;

            if($participant->tuition_centre_id && auth()->user()->hasRole(['Super Admin', 'Admin', 'Country Partner', 'Country Partner Assistant'])) {
                if($request->for_partner) {
                    $tuition_centre_id = School::where(
                        [
                            'name'              => 'Organization School',
                            'organization_id'   => $participant->competition_organization->organization()->value('id'),
                            'country_id'        => $participant->country_id,
                            'province'          => null
                        ])
                        ->value('id');
                }
                $participant->tuition_centre_id = $tuition_centre_id ?? $request->tuition_centre_id;
                if($request->school_id) {
                    $participant->school_id = $request->school_id;
                }
            } else {
                $participant->school_id = $request->school_id;
            }

            if($request->filled('password')){
                $participant->password = Hash::make($request->password);
            }
            $participant->save();

            return response()->json([
                "status" => 200 ,
                "message" => "participant update successful"
            ]);
        }

        catch(\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "participannt update unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function delete (DeleteParticipantRequest $request) {
        try {
            $deletedRecords = Participants::destroy($request->id);
            return response()->json([
                "status"    => 200,
                "message"   => "$deletedRecords Participants delete successful"
            ]);
        }
       catch (\Exception $e) {
           return response()->json([
               "status"     => 500,
               "message"    => "Participants delete unsuccessful",
               "error"      => $e->getMessage()
           ], 500);
       }
    }

    public function swapIndex (Request $request) {

        try {
            $validated = $request->validate([
                "index" => 'required|integer|exists:participants,index_no',
                "indexToSwap" => 'required|integer|exists:participants,index_no',
            ]);

            $results = Participants::whereIn("index_no",[$validated["index"],$validated["indexToSwap"]])
                ->get();

            if(count($results) == 2) {
                if($results[0]->country_id == $results[1]->country_id && $results[0]->competition_id == $results[1]->competition_id) {
                    $index = Participants::find($results[0])->first();
                    $indexToSwap = Participants::find($results[1])->first();

                    $temp1 = $index->index_no;
                    $temp2 = $indexToSwap->index_no;

                    $index->index_no = "000000000000";
                    $index->save();

                    $indexToSwap->index_no = $temp1;
                    $indexToSwap->save();

                    $index->index_no = $temp2;
                    $index->save();

                }

                return response()->json([
                    "status" => 200,
                    "message" => "Participant index number swap successful",
                ]);
            }

            return response()->json([
                "status" => 400,
                "message" => "Invaild index number"
            ]);
        }
        catch(ModelNotFoundException $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Participants index number swap unsuccessful"
            ]);
        }
    }

    public function performanceReportWithIndexAndCertificate(ParticipantReportWithCertificateRequest $request)
    {
        try {
            $participantResult = CompetitionParticipantsResults::where('participant_index', $request->index_no)
                ->with('participant')->firstOrFail()->makeVisible('report');

            if(is_null($participantResult->report)){
                $__report = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
                $report = $__report->getJsonReport();
                $participantResult->report = $report;
                $participantResult->save();
            }else{
                $report = $participantResult->report;
            }

            if($request->has('as_pdf') && $request->as_pdf == 1){
                $pdf = PDF::loadView('performance-report', [
                    'general_data'                  => $report['general_data'],
                    'performance_by_questions'      => $report['performance_by_questions'],
                    'performance_by_topics'         => $report['performance_by_topics'],
                    'grade_performance_analysis'    => $report['grade_performance_analysis'],
                    'analysis_by_questions'         => $report['analysis_by_questions']
                ]);
                return $pdf->download(sprintf("%s-report.pdf", $participantResult->participant->name));
            }

            return response()->json([
                "status"    => 200,
                "message"   => "Report generated successfully",
                "data"      => $report
            ]);

        } catch (Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Report generation is unsuccessfull",
                "error"     => $e->getMessage()
            ], 500);
        }
    }
}
