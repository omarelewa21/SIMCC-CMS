<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\Participants;
use App\Models\Countries;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\DeleteParticipantRequest;
use App\Http\Requests\getParticipantListRequest;
use App\Http\Requests\Participant\CreateParticipantRequest;
use App\Http\Requests\Participant\EliminateFromComputeRequest;
use App\Http\Requests\ParticipantReportWithCertificateRequest;
use App\Models\CompetitionParticipantsResults;
use App\Models\EliminatedCheatingParticipants;
use App\Rules\CheckSchoolStatus;
use App\Rules\CheckCompetitionAvailGrades;
use App\Rules\CheckParticipantGrade;
use App\Rules\CheckUniqueIdentifierWithCompetitionID;
use App\Services\Participant\CreateParticipantService;
use App\Services\ParticipantReportService;
use Exception;
use Illuminate\Validation\Rule;
use PDF;

class ParticipantsController extends Controller
{
    public function create(CreateParticipantRequest $request)
    {
        DB::beginTransaction();

        try {
            $newCreatedParticipants = array();
            foreach($request->participant as $data) {
                $newParticipant = (new CreateParticipantService((array) $data))->create();
                $newCreatedParticipants[] = $newParticipant;
            }
        }

        catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "Create Participants unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }

        DB::commit();
        return response()->json([
            "status"    => 201,
            "message"   => "create Participants successful",
            "data"      => $newCreatedParticipants
        ]);
    }

    public function list(getParticipantListRequest $request)
    {
        $participantCollection = Participants::leftJoin('users as created_user', 'created_user.id', '=', 'participants.created_by_userid')
            ->leftJoin('users as modified_user', 'modified_user.id', '=', 'participants.last_modified_userid')
            ->leftJoin('all_countries', 'all_countries.id', '=', 'participants.country_id')
            ->leftJoin('schools', 'schools.id', '=', 'participants.school_id')
            ->leftJoin('schools as tuition_centre', 'tuition_centre.id', '=', 'participants.tuition_centre_id')
            ->leftJoin('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->leftJoin('organization', 'organization.id', '=', 'competition_organization.organization_id')
            ->leftJoin('competition', 'competition.id', '=', 'competition_organization.competition_id')
            ->leftJoin('competition_participants_results', 'competition_participants_results.participant_index', '=', 'participants.index_no')
            ->leftJoin('participant_answers', function ($join) {
                $join->on('participant_answers.participant_index', '=', 'participants.index_no');
            })
            ->select(
                'participants.*',
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
                DB::raw("IF(competition_participants_results.published = 1, competition_participants_results.award, '-') AS award"),
                DB::raw('(COUNT(participant_answers.participant_index) > 0) as is_answers_uploaded')
            )
            ->filterList($request)
            ->groupBy('participants.id')
            ->get();
        try {
            if ($request->limits == "0") {
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
            $availSchools = $participantCollection->map(function ($item) {
                return ['id' => $item->school_id, 'name' => $item->school_name];
            })->whereNotNull('id')->unique()->sortBy('name')->values();

            /**
             * EOL Lists of availabe filters
             */

            if ($request->has('competition_id')) {
                /** addition filtering done in collection**/
                $participantCollection = $this->filterCollectionList(
                    $participantCollection,
                    [
                        "0,competition_id" => $request->competition_id ?? false, // 0 = non-nested, 1 = nested
                    ],
                    "competition_id"
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
                        'competition'   => $availCompetition,
                        'schools'       => $availSchools
                    ],
                    "participantList" => $participantList
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "The filter entered doesn't return any data, please change field parameters and try again",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function update (Request $request) {
        //password must English uppercase characters (A – Z), English lowercase characters (a – z), Base 10 digits (0 – 9), Non-alphanumeric (For example: !, $, #, or %), Unicode characters
        $participant = auth()->user()->hasRole(['Super Admin', 'Admin'])
            ? Participants::whereId($request['id'])->firstOrFail()
            : Participants::whereId($request['id'])->firstOrFail();

        $participantCountryId = $participant->country_id;
        $request['school_type'] = $participant->tuition_centre_id ? 1 : 0;

        $validate = array(
            'for_partner' => 'required_if:school_type,1|exclude_if:school_type,0|boolean',
            'name' => 'required|string|min:3|max:255',
            'class' => "max:20",
            'grade' => ['required', 'integer', 'min:1', 'max:99', new CheckParticipantGrade],
            'school_type' => ['required', Rule::in(0, 1)],
            'email' => ['sometimes', 'email', 'nullable'],
            "tuition_centre_id" => ['exclude_if:for_partner,1', 'exclude_if:school_type,0', 'integer', 'nullable', new CheckSchoolStatus(1, $participantCountryId)],
            "school_id" => ['required_if:school_type,0', 'integer', 'nullable', new CheckSchoolStatus(0, $participantCountryId)],
            'password' => ['confirmed', 'min:8', 'regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%@]).*$/'],
            "identifier" => [new CheckUniqueIdentifierWithCompetitionID($participant)],
        );


        switch (auth()->user()->role_id) {
            case 0:
            case 1:
                $validate['id'] = ["required", Rule::exists('participants', 'id'), "integer"];
                break;
            case 2:
            case 4:
                $organizationId = auth()->user()->organization_id;
                $countryId = auth()->user()->country_id;
                $activeCompetitionOrganizationIds = CompetitionOrganization::where(['organization_id' => $organizationId, 'status' => 'active'])->pluck('id')->toArray();
                $validate['id'] = ["required", "integer", Rule::exists('participants', 'id')->where("country_id", $countryId)->whereIn("competition_organization_id", $activeCompetitionOrganizationIds)];
                break;
            case 3:
            case 5:
                $schoolId = auth()->user()->school_id;
                $validate['id'] = ["required", "integer", Rule::exists('participants', 'id')->where("school_id", $schoolId)];
                break;
        }

        $validated = $request->validate($validate);
        try {
            $participant->name  = $request->name;
            $participant->grade = $request->grade;
            $participant->class = $request->class;
            $participant->email = $request->email;
            $participant->identifier = $request->identifier;

            if ($participant->tuition_centre_id && auth()->user()->hasRole(['Super Admin', 'Admin', 'Country Partner', 'Country Partner Assistant'])) {
                if ($request->for_partner) {
                    $tuition_centre_id = School::where(
                        [
                            'name'              => 'Organization School',
                            'organization_id'   => $participant->competition_organization->organization()->value('id'),
                            'country_id'        => $participant->country_id,
                            'province'          => null
                        ]
                    )
                        ->value('id');
                }
                $participant->tuition_centre_id = $tuition_centre_id ?? $request->tuition_centre_id;
                if ($request->school_id) {
                    $participant->school_id = $request->school_id;
                }
            } else {
                $participant->school_id = $request->school_id;
            }

            if ($request->filled('password')) {
                $participant->password = Hash::make($request->password);
            }
            $participant->save();

            return response()->json([
                "status" => 200,
                "message" => "participant update successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "participannt update unsuccessful",
                "error"     => $e->getMessage()
            ], 500);
        }
    }

    public function delete(DeleteParticipantRequest $request)
    {
        try {
            $deletedRecords = Participants::destroy($request->id);
            return response()->json([
                "status"    => 200,
                "message"   => "$deletedRecords Participants delete successful"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status"     => 500,
                "message"    => "Participants delete unsuccessful",
                "error"      => $e->getMessage()
            ], 500);
        }
    }

    public function swapIndex(Request $request)
    {

        try {
            $validated = $request->validate([
                "index" => 'required|integer|exists:participants,index_no',
                "indexToSwap" => 'required|integer|exists:participants,index_no',
            ]);

            $results = Participants::whereIn("index_no", [$validated["index"], $validated["indexToSwap"]])
                ->get();

            if (count($results) == 2) {
                if ($results[0]->country_id == $results[1]->country_id && $results[0]->competition_id == $results[1]->competition_id) {
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
        } catch (ModelNotFoundException $e) {
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

            if (is_null($participantResult->report)) {
                $__report = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
                $report = $__report->getJsonReport();
                $participantResult->report = $report;
                $participantResult->save();
            } else {
                $report = $participantResult->report;
            }

            if ($request->has('as_pdf') && $request->as_pdf == 1) {
                $report['general_data']['is_private'] = $participantResult->participant->tuition_centre_id ? true : false;
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

    public function eliminateParticipantsFromCompute(EliminateFromComputeRequest $request)
    {
        DB::beginTransaction();
        try {
            foreach ($request->participants as $participant_index) {
                EliminatedCheatingParticipants::updateOrCreate(
                    ['participant_index' => $participant_index],
                    ['reason' => $request->reason]
                );
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "Participants elimination is unsuccessfull",
                "error"     => $e->getMessage()
            ], 500);
        }
        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "Participants eliminated successfully"
        ]);
    }

    public function deleteEliminatedParticipantsFromCompute(EliminateFromComputeRequest $request)
    {
        DB::beginTransaction();
        try {
            EliminatedCheatingParticipants::whereIn('participant_index', $request->participants)
                ->delete();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"    => 500,
                "message"   => "Participants deletetion from elimination is unsuccessfull",
                "error"     => $e->getMessage()
            ], 500);
        }
        DB::commit();
        return response()->json([
            "status"    => 200,
            "message"   => "Participants deleted from elimination successfully"
        ]);
    }
}
