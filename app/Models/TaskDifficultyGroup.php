<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Model;

class TaskDifficultyGroup extends Base
{
    use HasFactory;
    use Filterable;
    const STATUS_VERIFIED = "verified";
    const STATUS_DELETED = "deleted";
    const STATUS_ACTIVE = "active";

    protected $table = "difficulty_groups";
    protected $guarded = [];

    private static $whiteListFilter = [
        'status',
    ];

    public function difficulty () {
        return $this->hasMany(TaskDifficulty::class, 'difficulty_groups_id', 'id');
}

}

