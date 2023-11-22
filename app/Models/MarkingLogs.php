<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarkingLogs extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function computedBy(): Attribute
    {
        return $this->user()->value('name');
    }

    public function level()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id');
    }

    public function group()
    {
        return $this->belongsTo(CompetitionMarkingGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'computed_by');
    }
}
