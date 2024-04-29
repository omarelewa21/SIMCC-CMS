<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EliminatedCheatingParticipants extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $timestamps = false;

    protected $appends = [
        'eliminated_by'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d',
    ];

    protected function eliminatedBy(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) =>
                $attributes['created_by']
                ? sprintf("%s %s", User::find($attributes['created_by'])->name, date("d/m/Y", strtotime($attributes['created_at'])))
                : '-'
        );
    }

    public static function booted(){
        static::creating(function($eliminatedCheatingParticipant) {
            $eliminatedCheatingParticipant->created_by = auth()->id();
            $eliminatedCheatingParticipant->created_at = now();
        });
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class, 'participant_index', 'index_no');
    }
}
