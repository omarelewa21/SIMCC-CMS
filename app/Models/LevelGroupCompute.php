<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LevelGroupCompute extends Model
{
    use HasFactory;

    protected $table = "level_group_compute";

    protected $guarded = [];

    const STATUS_NOT_STARTED  = "Not Started";
    const STATUS_In_PROGRESS  = "In Progress";
    const STATUS_FINISHED     = "Finished";
    const STATUS_BUG_DETECTED = "Bug Detected";

    public function updateStatus($status, $error_message=null)
    {
        switch ($status) {
            case self::STATUS_In_PROGRESS:
                $progress = 1;
                break;
            case self::STATUS_FINISHED:
                $progress = 100;
                break;
            default:
                $progress = $this->compute_progress_percentage;
                break;
        }
        $this->update([
            'computing_status'              => $status,
            'compute_error_message'         => $error_message,
            'compute_progress_percentage'   => $progress
        ]);
    }
}
