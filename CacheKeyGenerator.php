<?php

namespace App\Support\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CacheKeyGenerator
{
    public static function forList(
        Builder $builder,
        Request $request,
        string $prefix = 'list',
        string $version = 'v1'
    ): string {
        $model = $builder->getModel();

        $baseTag = static::modelBaseTag($model);
        $fingerprint = md5(
            $builder->toSql() .
            serialize($builder->getBindings()) .
            $request->fullUrl() .
            json_encode($request->query())
        );

        return "{$baseTag}:{$prefix}:{$version}:{$fingerprint}";
    }

    public static function forSingle(mixed $model, string $version = 'v1'): string
    {
        $base = static::modelBaseTag($model);
        return "{$base}:single:{$version}:{$model->getKey()}";
    }

    public static function modelBaseTag(mixed $model): string
    {
        $class = is_object($model) ? $model::class : $model;

        $module = null;
        if (preg_match('/Modules\\\\([^\\\\]+)\\\\(?:Models|Entities)?\\\\/', $class, $m)) {
            $module = Str::kebab($m[1]);
        }

        $modelName = Str::kebab(class_basename($class));

        return $module ? "{$module}.{$modelName}" : $modelName;
    }
}
