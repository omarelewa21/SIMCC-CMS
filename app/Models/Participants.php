<?php

namespace App\Models;

use App\Http\Requests\getParticipantListRequest;
use App\Models\Scopes\DiscardElminatedParticipantsAnswersScope;
use Carbon\Carbon;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Participants extends Base
{
    use HasFactory;
    use Filterable;

    private static $whiteListFilter = [
        'status',
        'index_no',
        'country_id',
        'grade',
        'competition_id',
        'school_id'
    ];

    protected $table = 'participants';

    protected $guarded = [];

    protected $hidden = [
        'password',
        "last_modified_userid"
    ];

    public static function booted()
    {
        parent::booted();

        static::saving(function($participant) {
            $participant->last_modified_userid = auth()->id();
        });
        
        static::creating(function($participant) {
            $participant->created_by_userid = auth()->id();
        });
    }

    /**
     * Scope a query to request params
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  getParticipantListRequest $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterList($query, getParticipantListRequest $request)
    {
        switch(auth()->user()->role_id) {
            case 2:
            case 4:
                $ids = CompetitionOrganization::where('organization_id', auth()->user()->organization_id)->pluck('id');
                $query->where('participants.country_id', auth()->user()->country_id)
                    ->whereIn("participants.competition_organization_id", $ids);
                break;
            case 3:
            case 5:
                $ids = CompetitionOrganization::where([
                    'country_id'        => auth()->user()->country_id,
                    'organization_id'   => auth()->user()->organization_id
                ])->pluck('id')->toArray();
                $query->whereIn("competition_organization_id", $ids)->where("tuition_centre_id" , auth()->user()->school_id)
                    ->orWhere("schools.id" , auth()->user()->school_id);
                break;
        }

        foreach($request->all() as $key=>$value){
            switch($key) {
                case 'search':
                    $query->where(function($query) use($value){
                        $query->where('participants.index_no', $value)
                            ->orWhere('participants.name', 'like', "%$value%")
                            ->orWhere('schools.name', 'like', "%$value%")
                            ->orWhere('tuition_centre.name', 'like', "%$value%");
                    });
                    break;
                case 'private':
                    $request->private
                        ? $query->whereNotNull("tuition_centre_id")
                        : $query->whereNull("tuition_centre_id");
                    break;
                case 'country_id':
                case 'school_id':
                case 'grade':
                case 'status':
                    $query->where("participants.$key", $value);
                    break;
                case 'organization_id':
                    $query->where('organization.id', $value);
                    break;
                case 'competition_id':
                    $query->where('competition.id', $value);
                    break;
                case 'page':
                case 'limits':
                    break;
                default:
                    $query->where($key, $value);
            }
        }

    }

    public function created_by () {
        return $this->belongsTo(User::class,"created_by_userid","id");
    }

    public function modified_by () {
        return $this->belongsTo(User::class,"last_modified_userid","id");
    }

    public function tuition_centre ()
    {
        return $this->belongsTo(School::class,"tuition_centre_id","id");
    }

    public function school ()
    {
        return $this->belongsTo(School::class,"school_id","id");
    }

    public function country ()
    {
        return $this->belongsTo(Countries::class, 'country_id');
    }

    public function competition_organization () {
        return $this->belongsTo(CompetitionOrganization::class,"competition_organization_id","id");
    }

    public function isCheater()
    {
        return $this->hasOne(EliminatedCheatingParticipants::class, 'participant_index', 'index_no');
    }

    public function answers()
    {
        return $this->hasMany(ParticipantsAnswer::class, 'participant_index', 'index_no')->withoutGlobalScope(new DiscardElminatedParticipantsAnswersScope);
    }

    public static function generateIndexNo(Countries $country, $isPrivate=false)
    {
        // Get the dial code for the country
        $dial = str_pad($country->Dial, 3, '0', STR_PAD_LEFT);

        // Get the current year as two digits
        $year = Carbon::now()->format('y');

        // Generate the search index
        $searchIndex = $dial . $year . ($isPrivate ? '1' : '0');

        // Check if the latest participant is private or non-private
        $latestParticipant = static::where('index_no', 'like', $searchIndex . '%')
            ->orderByDesc('id')
            ->first();

        // Get the latest index number for the same country
        $latestIndexNo = $latestParticipant ? substr($latestParticipant->index_no, -6) : '000000';

        // Generate the new index number
        $indexNo = $searchIndex . str_pad($latestIndexNo + 1, 6, '0', STR_PAD_LEFT);

        return $indexNo;
    }

    public static function generateCertificateNo()
    {
        $lastCertificateNo = static::latest('certificate_no')->value('certificate_no') ?? 'A0000000';

        $lastCharacter = substr($lastCertificateNo, 0, 1);
        $lastNumber = substr($lastCertificateNo, 1);

        if ($lastNumber == '9999999') {
            $newCharacter = chr(ord($lastCharacter) + 1);
            $newNumber = '0000000';
        } else {
            $newCharacter = $lastCharacter;
            $newNumber = str_pad((int) $lastNumber + 1, 7, '0', STR_PAD_LEFT);
        }

        return $newCharacter . $newNumber;
    }
}
