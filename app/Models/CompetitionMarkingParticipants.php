<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionMarkingParticipants extends Model
{
    use HasFactory;

    protected $table = "competition_marking_participants";
    protected $guarded = [];
}
