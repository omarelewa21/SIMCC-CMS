<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionParticipantsResults extends Model
{
    use HasFactory;

    protected $table = 'competition_participants_results';
    protected $guarded = [];
    protected $hidden = ['report'];

    protected $casts = [
        'report'    => AsArrayObject::class,
    ];

    public function scopeFilterByLevelAndGroup($query, $level, $group, $onlyResultComputedParticipants=true)
    {
        return $query->where('level_id', $level)->where('group_id', $group)
            ->when($onlyResultComputedParticipants, function ($query) {
                $query->onlyResultComputedParticipants();
            });
    }

    public function scopeOnlyResultComputedParticipants($query)
    {
        return $query->whereRelation('participant', 'status', Participants::STATUS_RESULT_COMPUTED);
    }

    public function competitionLevel()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id');
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class,'participant_index','index_no');
    }

    public function integrityCases()
    {
        return $this->hasMany(IntegrityCase::class, 'participant_index', 'participant_index');
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value !== Participants::STATUS_CHEATING || $this->intgrityCases?->isEmpty()) return $value;
                $status = collect([]);
                foreach($this->integrityCases as $case) {
                    switch($case->mode){
                        case 'map':
                            $status->push('MAP IAC');
                            break;
                        case 'custom':
                            $status->push('IAC Incident');
                            break;
                        default:
                            $status->push('System Generated IAC');
                            break;
                    }
                }

                return $status->join(', ', ' and ');
            }
        );
    }
}
