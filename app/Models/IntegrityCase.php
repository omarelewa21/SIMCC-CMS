<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrityCase extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function booted()
    {
        parent::booted();
        static::saving(function ($integrityCase) {
            $integrityCase->created_by = auth()->id();
        });
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class);
    }
}
