<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AwardsStaticsResults extends Model
{
    use HasFactory;
    protected $table = 'group_awards_statics_results';
    protected $fillable = ['group_id', 'competition_id', 'result'];
    protected $casts = [
        'result' => 'array'
    ];
}
