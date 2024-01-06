<?php

namespace App\Services\Competition;

use App\Models\Competition;
use App\Models\ParticipantsAnswer;
use App\Services\GradeService;
use Illuminate\Http\Request;

class ParticipantAnswersListService
{
    public function __construct(
        private Competition $competition,
        private Request $request
    ){}

    public function getFilterOptions()
    {
        $availableStatusses = $this->getAvailableStatusses();
        $availableGrades = GradeService::getAvailableCorrespondingGradesFromList($this->getAvailableGrades());
        $availableCountries = $this->getAvailableCountries();
        return [
            'status' => $availableStatusses,
            'grade' => $availableGrades,
            'country' => $availableCountries
        ];
    }

    private function getParticipantsQuery()
    {
        return $this->competition->participants()
            ->leftJoin('schools', 'schools.id', '=', 'participants.school_id')
            ->leftJoin('schools as tuition_centre', 'tuition_centre.id', '=', 'participants.tuition_centre_id')
            ->leftJoin('all_countries', 'all_countries.id', '=', 'participants.country_id')
            ->filterList($this->request);
    }

    private function getAvailableStatusses(): array
    {
        return (clone $this->getParticipantsQuery())
            ->select('participants.status')
            ->distinct()
            ->get()
            ->pluck('status')
            ->toArray();
    }

    private function getAvailableGrades(): array
    {
        return (clone $this->getParticipantsQuery())
            ->select('participants.grade')
            ->orderBy('participants.grade')
            ->distinct()
            ->get()
            ->pluck('grade')
            ->toArray();
    }

    private function getAvailableCountries(): array
    {
        return (clone $this->getParticipantsQuery())
            ->select('participants.country_id', 'all_countries.display_name as country_name')
            ->distinct('participants.country_id')
            ->get()
            ->map(fn($country) => [
                'id' => $country->country_id,
                'name' => $country->country_name
            ])
            ->toArray();
    }

    public function getList()
    {
        return (clone $this->getParticipantsQuery())
            ->with('answers', 'school', 'country')
            ->withCount('answers')
            ->paginate($this->request->limits ?? 10);
    }

    public function getHeaders($data)
    {
        $headers = [
            'Index No',
            'Name',
            'School',
            'Country',
            'Grade',
            'Status',
            'Answers Count'
        ];

        $questionsCount = $data->pluck('answers')->max()->count();
        for ($i = 1; $i <= $questionsCount; $i++) {
            $headers[] = "Q$i";
        }

        return $headers;
    }

    public function deleteParticipantsAnswers()
    {
        $indexes = $this->getParticipantsQuery()
            ->when(
                $this->request->indexes,
                fn($query) => $query->whereIn('participants.index_no', $this->request->indexes)
            )
            ->pluck('participants.index_no');

        ParticipantsAnswer::whereIn('participant_index', $indexes)->delete();
    }

    public static function getAnswersReportName(Competition $competition)
    {
        return "answers_report_{$competition->name}.xlsx";
    }

    public function getAnswerReportData()
    {
        return $this->competition->participants()
            ->whereIn('participants.country_id', $this->request->countries)
            ->where('participants.grade', $this->request->grade)
            ->with(['answers' => fn($q) => $q->orderBy('task_id'), 'country:id,display_name as name'])
            ->select(
                'participants.index_no', 'participants.name', 'participants.grade',
                'participants.country_id'
            )
            ->get()
            ->map(function($participant) {
                $data['index'] = $participant->index_no;
                $data['name'] = $participant->name;
                $data['grade'] = GradeService::AvailableGrades[$participant->grade];
                $data['country'] = $participant->country->name;
                foreach($participant->answers as $index=>$answer) {
                    $data["Q" . ($index + 1)] = sprintf("%s (%s)", $answer->answer, $answer->is_correct ? 'Correct' : 'Incorrect');
                }
                $data['total_score'] = $participant->answers->sum('score');
                return $data;
            });
    }

    public function getAnswerReportHeaders()
    {
        $headers = [
            'Index No',
            'Name',
            'Grade',
            'Country'
        ];

        $level = $this->competition->levels()
            ->whereJsonContains('grades', intval($this->request->grade))
            ->with('collection.sections')
            ->first();

        $answerKeys = $level->collection->sections
            ->pluck('section_task')
            ->flatten()
            ->map(function($task) {
                return $task->getCorrectAnswerKey();
            });        
        
        foreach($answerKeys as $index=>$answerKey) {
            $headers[] = sprintf("Q%s (%s)", $index+1, $answerKey);
        }

        $headers[] = sprintf("Total Score (%s)", $level->maxPoints());

        return $headers;
    }
}
