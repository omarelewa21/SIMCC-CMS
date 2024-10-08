<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionRounds extends Model
{
    use HasFactory;

    const AWARD_TYPE_POSITION = 0;
    const AWARD_TYPE_PERCENTAGE = 1;

    Protected $table = 'competition_rounds';
    protected $guarded = [];

    public static function booted()
    {
        parent::booted();

        static::deleted(function($record) {
            if($record->competition->rounds()->count() < 2){
                foreach($record->competition->overallAwardsGroups as $overallAwardsGroup){
                    $overallAwardsGroup->overallAwards()->delete();
                    $overallAwardsGroup->delete();
                }
                $record->levels()->delete();
            }
        });
    }

    public function getAwardTypeAttribute($value)
    {
        switch ($value) {
            case self::AWARD_TYPE_POSITION:
                return 'Position';
                break;
            default:
                return 'Percentage';
                break;
        }
    }

    public function competition () {
        return $this->belongsTo(Competition::class,'competition_id','id');
    }

    public function levels () {
        return $this->hasMany(CompetitionLevels::class,'round_id','id');
    }

    public function roundsAwards () {
        return $this->hasMany(CompetitionRoundsAwards::class,'round_id','id')->orderBy('id');
    }

    public function roundOverallAwards () {
        return $this->hasOne(CompetitionOverallAwards::class,'round_id','id');
    }
}
