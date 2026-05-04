<?php

namespace App\Traits;

use App\Support\Cache\CacheService;
use Illuminate\Database\Eloquent\Model;

trait HasListCacheInvalidation
{
    protected static function booted()
    {
        $handler = function (Model $model) {
            CacheService::invalidateModelListsAndSingle($model);
        };

        static::saved($handler);
        static::deleted($handler);
    }
}
