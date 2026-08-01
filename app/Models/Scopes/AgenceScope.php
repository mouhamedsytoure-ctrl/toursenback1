<?php

namespace App\Models\Scopes;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AgenceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $agenceId = Tenant::agenceId();

        if ($agenceId !== null) {
            $builder->where($model->getTable() . '.agence_id', $agenceId);
        }
    }
}
