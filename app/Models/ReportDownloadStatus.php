<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportDownloadStatus extends Model
{
    use HasFactory;
    protected $table = 'report_download_status';
    protected $fillable = ['job_id', 'progress_percentage', 'status', 'file_path', 'report'];
}
