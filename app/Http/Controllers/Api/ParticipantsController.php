<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use App\Models\Participants;
use App\Models\Countries;
use App\Helpers\General\CollectionHelper;
use App\Http\Requests\DeleteParticipantByIndexRequest;
use App\Http\Requests\DeleteParticipantRequest;
use App\Http\Requests\getParticipantListRequest;
use App\Http\Requests\Participant\EliminateFromComputeRequest;
use App\Jobs\GeneratePerformanceReports;
use App\Jobs\RecaculateShoolRankJob;
use App\Models\CompetitionParticipantsResults;
use App\Models\EliminatedCheatingParticipants;
use App\Models\ReportDownloadStatus;
use App\Rules\CheckSchoolStatus;
use App\Rules\CheckCompetitionAvailGrades;
use App\Rules\CheckParticipantGrade;
use App\Rules\CheckParticipantIndexNo;
use App\Rules\CheckParticipantIndexNoUniqueIdentifier;
use App\Rules\CheckSchoolName;
use App\Rules\CheckUniqueIdentifierWithCompetitionID;
use App\Rules\CheckUniqueIdentifierWithCountryId;
use App\Services\ParticipantReportService;
use Exception;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PDF;


class ParticipantsController extends Controller
{
    public function create(Request $request)
    {

        $request['role_id'] = auth()->user()->role_id;

        Countries::all()->map(function ($row) use (&$ccode) {
            $ccode[$row->id] = $row->Dial;
        });

        $validate = array(
            "role_id" => "nullable",
            "participant.*.competition_id" => ["required"],
            "participant.*.is_private" => "required|boolean",
            "participant.*.country_id" => 'exclude_if:role_id,2,3,4,5|required_if:role_id,0,1|integer|exists:all_countries,id',
            "participant.*.organization_id" => 'exclude_if:role_id,2,3,4,5|required_if:role_id,0,1|integer|exists:organization,id',
            "participant.*.name" => "required|string|max:255",
            "participant.*.class" => "required|max:255|nullable",
            "participant.*.grade" => ["required", "integer", new CheckCompetitionAvailGrades],
            "participant.*.for_partner" => "required|boolean",
            "participant.*.partner_userid" => "exclude_if:*.for_partner,0|required_if:*.for_partner,1|integer|exists:users,id",
            "participant.*.tuition_centre_id" => ['exclude_if:*.for_partner,1', 'required_if:*.school_id,null', 'integer', 'nullable', new CheckSchoolStatus(1)],
            "participant.*.school_id" => ['exclude_if:role_id,3,5', 'required_if:*.tuition_centre_id,null', 'nullable', 'integer', new CheckSchoolStatus],
            "participant.*.email"     => ['sometimes', 'email', 'nullable'],
            "participant.*.identifier" => [new CheckUniqueIdentifierWithCompetitionID(null)],
            "participant.*.online_based" => 'nullable|in:null,y',
            "participant.*.identifier" => [new CheckUniqueIdentifierWithCountryId(null)],

            // "participant.*.email"     => ['sometimes', 'email', new ParticipantEmailRule]
        );

        $messages = [
            "participant.*.online_based.in" => "The Online Based field must be 'null' or 'y'.",
        ];

        $validated = $request->validate($validate, $messages);

        $validated = data_fill($validated, 'participant.*.class', null); // add missing class attribute and set to null

        try {
            DB::beginTransaction();

            $returnData = [];
            $validated = collect($validated['participant'])->map(function ($row, $index) use ($ccode, &$returnData) {

                switch (auth()->user()->role_id) {
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

                if (isset($tuitionCentreId)) {
                    $row["tuition_centre_id"] = $tuitionCentreId;
                    $row["school_id"] = $schoolId;
                } else {
                    $row["school_id"] = $schoolId;
                }

                if (isset($row["for_partner"]) && $row["for_partner"] == 1) {
                    $row["tuition_centre_id"] = School::where(['name' => 'Organization School', 'organization_id' => $organizationId, 'country_id' => $countryId, 'province' => null])
                        ->get()
                        ->pluck('id')
                        ->firstOrFail();
                }

                if ($row['is_private'] == 1 && is_null($row['tuition_centre_id'])) {
                    $row['tuition_centre_id'] = School::DEFAULT_TUITION_CENTRE_ID;
                }

                $country_id  = in_array(auth()->user()->role_id, [2, 3, 4, 5]) ? auth()->user()->country_id : $row["country_id"];
                $CountryCode = $ccode[$country_id];

                /*Generate index no.*/
                //$country = Countries::find($country_id);
                $country = Countries::where(['dial' => $CountryCode, 'update_counter' => 1])->first();
                $index = Participants::generateIndexNo($country, isset($row["tuition_centre_id"]) && $row["tuition_centre_id"]);
                $certificate = Participants::generateCertificateNo();

                $row['competition_organization_id'] = CompetitionOrganization::where(['competition_id' => $row['competition_id'], 'organization_id' => $organizationId])->firstOrFail()->id;
                $row['session'] = Competition::findOrFail($row['competition_id'])->competition_mode == 0 ? 0 : null;
                $row["country_id"] = $country_id;
                $row["created_by_userid"] =  auth()->id(); //assign entry creator user id
                $row["index_no"] = $index;
                $row["certificate_no"] = $certificate;
                // $row["password"] = Hash::make($filteredPasskey);
                $row["password"] = Participants::generatePassword();
                unset($returnData[count($returnData) - 1]['password']);
                unset($row['competition_id']);
                unset($row['for_partner']);
                unset($row['organization_id']);
                $participant = Participants::create($row);
                $returnData[] = $participant;
                return $row;
            })->toArray();

            DB::commit();

            return response()->json([
                "status" => 201,
                "message" => "create Participants successful",
                "data" => $returnData
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Create Participants unsuccessful" . $e->getMessage(),
                "error"     => strval($e)
            ], 500);
        }
    }

    public function list(getParticipantListRequest $request)
    {
        $participantCollection = Participants::leftJoin('all_countries', 'all_countries.id', 'participants.country_id')
            ->leftJoin('schools', 'schools.id', 'participants.school_id')
            ->leftJoin('schools as tuition_centre', 'tuition_centre.id', 'participants.tuition_centre_id')
            ->leftJoin('competition_organization', 'competition_organization.id', 'participants.competition_organization_id')
            ->leftJoin('organization', 'organization.id', 'competition_organization.organization_id')
            ->leftJoin('competition', 'competition.id', 'competition_organization.competition_id')
            ->leftJoin(
                'taggables',
                fn ($join) =>
                $join->on('taggables.taggable_id', 'competition.id')->where('taggables.taggable_type', 'App\Models\Competition')
            )
            ->leftJoin('competition_participants_results', 'competition_participants_results.participant_index', 'participants.index_no')
            ->leftJoin('participant_answers', 'participant_answers.participant_index', 'participants.index_no')
            ->selectRaw("
                participants.*,
                all_countries.display_name as country_name,
                CASE WHEN participants.tuition_centre_id IS NULL THEN 0 ELSE 1 END AS private,
                schools.id as school_id,
                schools.name as school_name,
                tuition_centre.id as tuition_centre_id,
                tuition_centre.name as tuition_centre_name,
                competition.id as competition_id,
                competition.name as competition_name,
                competition.alias as competition_alias,
                organization.id as organization_id,
                organization.name as organization_name,
                IF(competition_participants_results.published = 1, competition_participants_results.award, '-') AS award,
                COUNT(participant_answers.participant_index) > 0 as is_answers_uploaded
            ")
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

            $availTags = Competition::whereIn('competition.id', $availCompetition->pluck('id'))
                ->join(
                    'taggables',
                    fn ($join) =>
                    $join->on('taggables.taggable_id', 'competition.id')->where('taggables.taggable_type', 'App\Models\Competition')
                )
                ->join('domains_tags', 'domains_tags.id', 'taggables.domains_tags_id')
                ->select('domains_tags.id', 'domains_tags.name')
                ->groupBy('domains_tags.id')
                ->get()
                ->toArray();

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
                        'tags'          => $availTags,
                        'schools'       => $availSchools,
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

    public function update(Request $request)
    {
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
            "identifier" => [new CheckUniqueIdentifierWithCountryId($participant)],
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
                if ($participant->is_private && is_null($request->tuition_centre_id)) {
                    $participant->tuition_centre_id = School::DEFAULT_TUITION_CENTRE_ID;
                } else {
                    $participant->tuition_centre_id = $tuition_centre_id ?? $request->tuition_centre_id;
                }
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

    public function deleteByIndex(DeleteParticipantByIndexRequest $request)
    {
        try {
            $deletedRecords = Participants::whereIn('index_no', $request->indexes)->delete();
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


    public function performanceReportWithIndexAndCertificate(Request $request)
    {
        try {
            $participantResult = CompetitionParticipantsResults::where('participant_index', $request->index_no)
                ->with('participant')->firstOrFail()->makeVisible('report');

            if (is_null($participantResult->report)) {
                // Generate the report data
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
                $filename = $participantResult->participant->name . '-report.pdf';
                $pdfContent = $pdf->output();
                return view('performance-report-pdf')->with('pdfContent', $pdfContent)->with('filename', $filename);
            }

            return response()->json([
                "status"    => 200,
                "message"   => "Report generated successfully",
                "data"      => $report
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Report generation is unsuccessful",
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

    public function performanceReportsBulkDownload(getParticipantListRequest $request)
    {
        try {
            $response = $this->list($request);
            $data = json_decode($response->getContent());
            $participants = $data->data->participantList->data;
            if (count($participants) > 100) {
                throw new Exception('The total count of reports exceeds the established limit of 100 reports.');
            }
            $job = new GeneratePerformanceReports($participants);
            $job_id = $this->dispatch($job);
            return response()->json([
                "status"    => 200,
                'job_id' => $job_id,
                "message"   => "Report generation job dispatched"
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Bulk download reports failed :" . $e->getMessage()
            ]);
        }
    }

    public function performanceReportsBulkDownloadCheckProgress($jobId)
    {
        $job = ReportDownloadStatus::where('job_id', $jobId)->first();
        if ($job) {
            $progress = $job->progress_percentage;
            $report = $job->report;
            $status = $job->status;
            switch ($status) {
                case ReportDownloadStatus::STATUS_In_PROGRESS:
                    return response()->json([
                        'job_id' => $jobId,
                        'status' => ReportDownloadStatus::STATUS_In_PROGRESS,
                        'file_path' => '',
                        'progress' => $progress,
                    ], 200);
                case ReportDownloadStatus::STATUS_FAILED:
                    return response()->json([
                        'job_id' => $jobId,
                        'status' => ReportDownloadStatus::STATUS_FAILED,
                        'message' => 'Failed to generate' . (isset($report['public_error']) ? $report['public_error'] : ''),
                        'file_path' => '',
                        'progress' => $progress,
                    ], 200);
                case ReportDownloadStatus::STATUS_COMPLETED:
                    $filePath = 'performance_reports/' . $job->file_path;
                    if (!Storage::exists($filePath)) {
                        return response()->json([
                            'job_id' => $jobId,
                            'status' => ReportDownloadStatus::STATUS_FAILED,
                            'message' => 'Failed to generate' . (isset($report['public_error']) ? $report['public_error'] : ''),
                            'file_path' => '',
                            'progress' => 0,
                        ], 200);
                    }
                    return response()->json([
                        'job_id' => $jobId,
                        'status' => ReportDownloadStatus::STATUS_COMPLETED,
                        'file_path' => route('participant.reports.bulk_download.download_file', ['job_id' => $job->job_id]),
                        'progress' => $progress,
                    ], 200);
                    // return Response::download(storage_path('app/' . $job->file_path))->deleteFileAfterSend(true);
            }
        } else {
            return response()->json([
                'job_id' => $jobId,
                'status' => ReportDownloadStatus::STATUS_NOT_STARTED,
                'progress' => 0,
                'message' => ReportDownloadStatus::STATUS_NOT_STARTED,
            ], 200);
        }
    }

    public function performanceReportsBulkDownloadFile($id)
    {
        $job = ReportDownloadStatus::where('job_id', $id)->first();
        if (!$job) {
            return response()->json([
                'message' => 'Job not found',
            ], 404);
        }

        $filePath = 'performance_reports/' . $job->file_path;

        if (!Storage::exists($filePath)) {
            return response()->json([
                'message' => 'File not found',
            ], 404);
        }

        return Response::download(Storage::path($filePath))->deleteFileAfterSend(true);
    }
    public function bulkUpdateParticipants(Request $request)
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'participants' => ['required', 'array'],
                'participants.*.index_no' => ['required', new CheckParticipantIndexNo],
                'participants.*.name' => 'string|min:3|max:255',
                'participants.*.email' => 'sometimes|email|nullable',
                "participant.*.class" => "max:255|nullable",
                'participants.*.school_name' => ['sometimes', 'string', 'nullable', new CheckSchoolName],
                'participants.*.identifier' => [new CheckParticipantIndexNoUniqueIdentifier(
                    $request->input('participants')
                )],
            ]);

            if ($validator->fails()) {
                $errorMessages = $validator->errors();
                $errorData = [
                    'message' => $errorMessages->first() . ' (' . ($errorMessages->count() - 1) . ' more errors)',
                    'errors' => $errorMessages,
                ];
                return response()->json($errorData, 400);
            }

            $participantData = $request->participants;
            $response = [];
            $changedSchoolsIds = [];
            $participantsWithNewSchoolId = [];

            foreach ($participantData as $participant) {
                $participantIndex = $participant['index_no'];
                $participantToUpdate = Participants::where('index_no', $participantIndex)->first();
                if (isset($participant['school_name']) && $participant['school_name'] != null) {
                    $participantCountryId = $participantToUpdate->country_id;
                    $originalSchoolId = $participantToUpdate->school_id;
                    $newSchoolId = $this->getSchoolIdBySchoolName($participant['school_name'], $participantCountryId);
                    $participant['school_id'] = $newSchoolId;
                    // Check if the school is being changed
                    if ($originalSchoolId != $newSchoolId) {
                        $changedSchoolsIds[] = $originalSchoolId;
                        $changedSchoolsIds[] = $newSchoolId;
                        $participantsWithNewSchoolId[] = $participantToUpdate->index_no;
                    }
                    // Update the participant with the validated data
                    unset($participant['school_name']);
                }

                $participantToUpdate->update($participant);
                $participantToUpdate->save();
                $response[] = [
                    'index_no' => $participantIndex,
                    'message' => 'Participant updated successfully',
                ];
            }

            DB::commit();
            $changedSchoolsIds = array_unique($changedSchoolsIds);
            // Dispatch the job to recalculate school ranks and generate reports
            if (!empty($changedSchoolsIds)) {
                RecaculateShoolRankJob::dispatch($changedSchoolsIds, $participantsWithNewSchoolId);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Bulk update of participants completed successfully',
                'data' => $response,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update participants in bulk' . $e->getMessage(),
                'error' => $e,
            ], 500);
        }
    }

    public function getSchoolIdBySchoolName($schoolName, $participantCountryId)
    {
        $schools = School::where('name', 'LIKE', "%$schoolName%")->get();

        if ($schools->count() > 0) {
            if ($schools->count() === 1) {
                return $schools->first()->id;
            }

            $schoolsInCountry = $schools->where('country_id', $participantCountryId);

            if ($schoolsInCountry->count() > 0) {
                if ($schoolsInCountry->count() === 1) {
                    return $schoolsInCountry->first()->id;
                }

                $activeSchool = $schoolsInCountry->where('status', 'active')->first();
                return $activeSchool ? $activeSchool->id : null;
            }
        }

        return null;
    }
}
