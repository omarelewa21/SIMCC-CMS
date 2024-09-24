<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlagNotification extends Model
{
    use HasFactory;

    protected $table = 'flag_notifications';

    public const TYPE_RECOMPUTE = 'recompute';

    protected $fillable = [
        'competition_id',
        'level_id',
        'group_id',
        'type',
        'note',
        'status',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function level()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id');
    }
}
