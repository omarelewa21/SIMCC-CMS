<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrityCase extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i',
        'updated_at' => 'datetime:Y-m-d H:i',
     ];

    public static function booted()
    {
        parent::booted();
        static::saving(function ($integrityCase) {
            $integrityCase->created_by = auth()->id() ?? 2;
        });
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class);
    }

    protected function createdBy(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => User::whereId($value)->value('name')
        );
    }
}
