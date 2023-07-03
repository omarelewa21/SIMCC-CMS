<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AwardsStaticsStatus extends Model
{
    use HasFactory;
    protected $table = 'group_awards_statics_status';
    protected $fillable = ['group_id', 'progress_percentage', 'status', 'report'];
}
