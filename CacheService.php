<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CacheService
{
    public static function rememberList(
        Builder $builder,
        Request $request,
        Closure $dataCallback,
        int $ttlMinutes = 30,
        array $extraTags = [],
        string $prefix = 'list'
    ) {
        $key = CacheKeyGenerator::forList($builder, $request, $prefix);

        $modelTag = CacheKeyGenerator::modelBaseTag($builder->getModel());

        $tags = array_merge(
            ["{$modelTag}:lists", 'lists'],
            $extraTags
        );

        return Cache::tags($tags)->remember($key, now()->addMinutes($ttlMinutes), $dataCallback);
    }

    public static function invalidateModelListsAndSingle(mixed $model)
    {
        if (! $model instanceof \Illuminate\Database\Eloquent\Model) {
            return;
        }

        $baseTag = CacheKeyGenerator::modelBaseTag($model);

        Cache::tags(["{$baseTag}:lists", 'lists'])->flush();

        Cache::forget(CacheKeyGenerator::forSingle($model));
    }
}
