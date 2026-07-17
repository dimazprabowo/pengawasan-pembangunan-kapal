<?php

namespace App\Traits;

trait HasDynamicLike
{
    protected function getLikeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ilike' : 'like';
    }
}
