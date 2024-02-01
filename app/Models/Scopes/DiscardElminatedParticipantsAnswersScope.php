<?php

namespace App\Models\Scopes;

use App\Models\Participants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class DiscardElminatedParticipantsAnswersScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('participants')
                  ->where([
                    'participants.index_no' => 'participant_answers.participant_index',
                    'participants.status'   => Participants::STATUS_CHEATING
                  ]);
        });
    }
}
