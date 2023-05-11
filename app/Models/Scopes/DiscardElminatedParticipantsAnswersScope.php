<?php

namespace App\Models\Scopes;

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
                  ->from('eliminated_cheating_participants')
                  ->whereRaw('eliminated_cheating_participants.participant_index = participant_answers.participant_index');
        });
    }
}
