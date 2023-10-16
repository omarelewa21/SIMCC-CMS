<?php

namespace App\Services\Participant;

use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\Countries;
use App\Models\Participants;
use App\Models\Roles;
use App\Models\School;
use Illuminate\Support\Facades\Hash;

class CreateParticipantService
{
    function __construct(private array $data)
    {
    }

    public function create()
    {
        $data = $this->data;
        $userRole = Roles::find(auth()->user()->role_id)->name;
        switch (auth()->user()->role_id) {
            case 0:
            case 1:
                $organizationId = $data["organization_id"];
                $countryId = $data["country_id"];
            case 2:
            case 4:
                $organizationId = $organizationId ?? auth()->user()->organization_id;
                $countryId = $countryId ?? auth()->user()->country_id;
                $schoolId =  $data["school_id"];
                $tuitionCentreId = $data["tuition_centre_id"];
                break;
            case 3:
            case 5:
                $organizationId = auth()->user()->organization_id;
                $schoolId =  auth()->user()->school_id;
                break;
        }

        if (isset($tuitionCentreId)) {
            $data["tuition_centre_id"] = $tuitionCentreId;
            $data["school_id"] = $schoolId;
        } else {
            $data["school_id"] = $schoolId;
        }

        if (isset($data["for_partner"]) && $data["for_partner"] == 1) {
            $data["tuition_centre_id"] = School::where(['name' => 'Organization School', 'organization_id' => $organizationId, 'country_id' => $countryId, 'province' => null])
                ->get()
                ->pluck('id')
                ->firstOrFail();
        }

        $country_id  = in_array(auth()->user()->role_id, [2, 3, 4, 5]) ? auth()->user()->country_id : $data["country_id"];
        $country = Countries::find($country_id);

        /*Generate index no.*/
        $data["index_no"] = Participants::generateIndexNo($country, $data["is_private"]);
        $data["certificate_no"] = Participants::generateCertificateNo();

        $data['competition_organization_id'] = CompetitionOrganization::where(['competition_id' => $data['competition_id'], 'organization_id' => $organizationId])->firstOrFail()->id;
        $data['session'] = Competition::findOrFail($data['competition_id'])->competition_mode == 0 ? 0 : null;
        $data["country_id"] = $country_id;
        $data["created_by_userid"] =  auth()->id(); //assign entry creator user id
        $data["passkey"] = str()->random(8);
        $data["password"] = Hash::make($data["passkey"]);
        unset($data['passkey']);
        unset($data['competition_id']);
        unset($data['for_partner']);
        unset($data['organization_id']);
        $participant = Participants::create($data);
        $returnData[] = $participant;
        return $data;
    }
}
