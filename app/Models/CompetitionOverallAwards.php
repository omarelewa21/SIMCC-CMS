<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionOverallAwards extends Model
{
    use HasFactory;

    protected $table = "competition_overall_awards";
    protected $guarded = [];
    protected $appends =['awards'];

    public $timestamps = false;

    public function getAwardsAttribute () {
        return is_null(CompetitionRoundsAwards::find($this->competition_rounds_awards_id)) ? null :CompetitionRoundsAwards::find($this->competition_rounds_awards_id)->name ;
    }
}
