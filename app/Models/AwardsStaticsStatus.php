<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AwardsStaticsStatus extends Model
{
    use HasFactory;
    const STATUS_NOT_STARTED  = "Not Started";
    const STATUS_In_PROGRESS  = "In Progress";
    const STATUS_FINISHED     = "Finished";
    const STATUS_BUG_DETECTED = "Bug Detected";
    protected $table = 'group_awards_statics_status';
    protected $fillable = [
        'group_id',
        'progress_percentage',
        'status',
        'report'
    ];
}
