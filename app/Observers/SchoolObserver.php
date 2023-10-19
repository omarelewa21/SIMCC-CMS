<?php

namespace App\Observers;

use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\School;
use App\Services\ParticipantReportService;
use Exception;
use Illuminate\Support\Facades\Log;

class SchoolObserver
{
    /**
     * Handle the School "created" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function created(School $school)
    {
        //
    }

    /**
     * Handle the School "updated" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function updated(School $school)
    {
        if ($school->isDirty('name') || $school->isDirty('name_in_certificate')) {
            Log::info('SchoolObserver - updated method is running.');
            $participants = Participants::where('school_id', $school->id)->get();
            foreach ($participants as $participant) {
                try {
                    $participantResult = CompetitionParticipantsResults::where('participant_index', $participant->index_no)
                        ->with('participant')
                        ->first();

                    if ($participantResult) {
                        $reportService = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
                        $report = $reportService->getJsonReport();
                        $participantResult->report = $report;
                        $participantResult->save();
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
    }

    /**
     * Handle the School "deleted" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function deleted(School $school)
    {
        //
    }

    /**
     * Handle the School "restored" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function restored(School $school)
    {
        //
    }

    /**
     * Handle the School "force deleted" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function forceDeleted(School $school)
    {
        //
    }
}
