<?php

namespace App\Models;

use App\Http\Requests\getParticipantListRequest;
use Carbon\Carbon;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;


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

    public static function generateIndexNo(Countries $country, $isPrivate=false)
    {
        $dial_code = str_pad($country->Dial, 3, '0', STR_PAD_LEFT);
        $year = Carbon::now()->format('y');

        // construct the initial index_no based on the parameters
        $index_no = $isPrivate . $dial_code . $year . '000000';

        // get the latest record for the same country and private status
        $latest_participant = self::where('index_no', 'like', substr($index_no, 0, 7) . '%')
                                  ->orderByDesc('index_no')
                                  ->first();

        if ($latest_participant) {
            // increment the last 6 digits of the index_no by 1
            $last_six_digits = substr($latest_participant->index_no, -6);
            $new_six_digits = str_pad($last_six_digits + 1, 6, '0', STR_PAD_LEFT);
            $index_no = substr_replace($index_no, $new_six_digits, -6);
        }

        return $index_no;
    }

    public static function generateCertificateNo()
    {
        $lastCertificateNo = self::latest('certificate_no')->value('certificate_no') ?? 'A0000000';
        
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
