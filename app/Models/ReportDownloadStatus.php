<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportDownloadStatus extends Model
{
    use HasFactory;

    const STATUS_NOT_STARTED  = "Not Started";
    const STATUS_In_PROGRESS  = "In Progress";
    const STATUS_COMPLETED     = "Completed";
    const STATUS_FAILED = "Failed";

    protected $table = 'report_download_status';
    protected $fillable = ['job_id', 'progress_percentage', 'status', 'file_path', 'report'];
    protected $casts = [
        'report'=>'array'
    ];
}
