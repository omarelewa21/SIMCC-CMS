<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionOverallAwardsGroups extends Base
{
    use HasFactory;

    protected $table = "competition_overall_awards_groups";
    protected $guarded =[];

    public function competition () {
        return $this->belongsTo(Competition::class,'competition_id','id');
    }

    public function overallAwards () {
        return $this->hasMany(CompetitionOverallAwards::class,'competition_overall_awards_groups_id','id');
    }
}
