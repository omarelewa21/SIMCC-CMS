<?php

use App\Models\CheatingParticipants;
use App\Models\CompetitionParticipantsResults;
use App\Models\IntegrityCase;
use App\Models\IntegrityCheckCompetitionCountries;
use App\Models\IntegritySummary;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;

return new class extends Migration
{
    private $competitionId = 91;  // 2024 AMO
    private $organizationId = 39; // US SIU
    private $countryId = 240;     // United States

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->clearResults();
        $this->clearIntegrityCases();
        $this->clearAnswers();
    }

    private function clearResults()
    {
        CompetitionParticipantsResults::join('participants', 'participants.index_no', '=', 'competition_participants_results.participant_index')
            ->join('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->where('competition_organization.competition_id', $this->competitionId)
            ->where('competition_organization.organization_id', $this->organizationId)
            ->where('participants.country_id', $this->countryId)
            ->delete();

        Participants::join('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->where('competition_organization.competition_id', $this->competitionId)
            ->where('competition_organization.organization_id', $this->organizationId)
            ->where('participants.country_id', $this->countryId)
            ->update(['participants.status' => Participants::STATUS_ACTIVE]);
    }

    private function clearIntegrityCases()
    {
        IntegrityCase::join('participants', 'participants.index_no', '=', 'integrity_cases.participant_index')
            ->join('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->where('competition_organization.competition_id', $this->competitionId)
            ->where('competition_organization.organization_id', $this->organizationId)
            ->where('participants.country_id', $this->countryId)
            ->delete();

        CheatingParticipants::join('participants', function (JoinClause $join) {
            $join->on('participants.index_no', 'cheating_participants.participant_index')
                ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })->join('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->where('competition_organization.competition_id', $this->competitionId)
            ->where('competition_organization.organization_id', $this->organizationId)
            ->where('participants.country_id', $this->countryId)
            ->delete();

        IntegrityCheckCompetitionCountries::where('competition_id', $this->competitionId)
            ->where('country_id', $this->countryId)
            ->update([
                'is_computed' => 0,
                'is_confirmed' => 0,
                'confirmed_by' => null,
                'confirmed_at' => null,
            ]);

        IntegritySummary::whereId(141)->delete();
    }

    private function clearAnswers()
    {
        ParticipantsAnswer::join('participants', 'participants.index_no', '=', 'participant_answers.participant_index')
            ->join('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->where('competition_organization.competition_id', $this->competitionId)
            ->where('competition_organization.organization_id', $this->organizationId)
            ->where('participants.country_id', $this->countryId)
            ->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Nothing to do
    }
};
