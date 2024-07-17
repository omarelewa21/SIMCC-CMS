<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class StatusScope implements Scope
{
    function __construct(public string $status = 'Deleted', public string $comparison = '!=')
    {
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where("{$model->getTable()}.status", $this->comparison, $this->status);
    }
}