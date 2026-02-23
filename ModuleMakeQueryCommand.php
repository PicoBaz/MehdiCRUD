<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;


/**
 * creator : mehdi jaberi (PicoBaz)
 */
class ModuleMakeQueryCommand extends Command
{
    protected $signature = 'module:make-query
                            {name : The name of the query class}
                            {model : The model associated with the query}
                            {module? : The module name (optional, creates in Core if not provided)}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a new Query class for a module or Core (Auto-installs dependencies)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Query Class Generator...');
        $this->newLine();

        // ========================================
        $this->ensureBaseModuleQueryExists();
        $this->newLine();

        // ========================================

        $name = $this->argument('name');
        $model = $this->argument('model');
        $module = $this->argument('module');
        $force = $this->option('force');

        $queryName = Str::studly($name);
        $modelName = Str::studly($model);

        if (!Str::endsWith($queryName, 'Query')) {
            $queryName .= 'Query';
        }

        $this->line("   Query Class: {$queryName}");
        $this->line("   Model: {$modelName}");
        $this->line("   Location: " . ($module ? "Module ({$module})" : "Core"));
        $this->newLine();

        // ========================================

        if ($module) {
            $module = Str::studly($module);

            if (!$this->moduleExists($module)) {
                $this->error("❌ Module '{$module}' does not exist!");
                $this->info("💡 Create the module first: php artisan module:make {$module}");
                return self::FAILURE;
            }

            $path = $this->getModulePath($module, $queryName);
            $namespace = "Modules\\{$module}\\Queries";
            $modelNamespace = "Modules\\{$module}\\Models\\{$modelName}";

            $this->ensureModuleQueriesDirectoryExists($module);
        } else {
            $path = $this->getCorePath($queryName);
            $namespace = "App\\Queries";
            $modelNamespace = "App\\Models\\{$modelName}";

            $this->ensureCoreQueriesDirectoryExists();
        }

        if (File::exists($path) && !$force) {
            $this->error("❌ Query class already exists: {$path}");
            $this->info("💡 Use --force option to overwrite");
            return self::FAILURE;
        }

        $this->line("   Directory: " . dirname($path));
        $this->line("   File: " . basename($path));
        $this->newLine();

        // ========================================

        $content = $this->generateQueryClass(
            $namespace,
            $queryName,
            $modelName,
            $modelNamespace
        );

        File::put($path, $content);

        // ========================================
        $this->newLine();
        $this->info('✅ Query class created successfully!');
        $this->newLine();

        $this->newLine();
        return self::SUCCESS;
    }

    protected function ensureBaseModuleQueryExists(): void
    {
        $baseQueryPath = app_path('Queries/BaseModuleQuery.php');

        if (File::exists($baseQueryPath)) {
            $this->line('   ✓ BaseModuleQuery already exists');
            return;
        }

        $this->line('   ⚙️  Installing BaseModuleQuery...');

        $directory = dirname($baseQueryPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->line("   ✓ Created directory: {$directory}");
        }

        $content = $this->getBaseModuleQueryStub();
        File::put($baseQueryPath, $content);

        $this->info('   ✓ BaseModuleQuery installed successfully!');
    }

    protected function ensureCoreQueriesDirectoryExists(): void
    {
        $directory = app_path('Queries');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->line("   ✓ Created Core Queries directory");
        } else {
            $this->line("   ✓ Core Queries directory exists");
        }
    }


    protected function ensureModuleQueriesDirectoryExists(string $module): void
    {
        $directory = base_path("Modules/{$module}/app/Queries");

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->line("   ✓ Created Module Queries directory");
        } else {
            $this->line("   ✓ Module Queries directory exists");
        }
    }


    protected function moduleExists(string $module): bool
    {
        return File::isDirectory(base_path("Modules/{$module}"));
    }


    protected function getModulePath(string $module, string $queryName): string
    {
        return base_path("Modules/{$module}/app/Queries/{$queryName}.php");
    }


    protected function getCorePath(string $queryName): string
    {
        return app_path("Queries/{$queryName}.php");
    }


    protected function generateQueryClass(
        string $namespace,
        string $queryName,
        string $modelName,
        string $modelNamespace
    ): string {
        $stub = $this->getQueryStub();

        $replacements = [
            '{{namespace}}' => $namespace,
            '{{queryName}}' => $queryName,
            '{{modelName}}' => $modelName,
            '{{modelNamespace}}' => $modelNamespace,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );
    }


    protected function getQueryStub(): string
    {
        return <<<'STUB'
<?php

namespace {{namespace}};

use App\Queries\BaseModuleQuery;
use {{modelNamespace}};
use Illuminate\Http\Request;

/**
 * Query class for {{modelName}} model
 *
 * @method static static withRelations(Request $request, array $allowed = [])
 * @method static static filter(Request $request)
 * @method static static sort(Request $request, string $default = 'id')
 * @method static static where($column, $operator = null, $value = null)
 * @method static static whereIn(string $column, array $values)
 * @method static \Illuminate\Contracts\Pagination\CursorPaginator paginate(int $perPage = 15)
 * @method static \Illuminate\Database\Eloquent\Builder getQuery()
 * @method static \Illuminate\Database\Eloquent\Collection get()
 * @method static \Illuminate\Database\Eloquent\Model|null first()
 * @method static int count()
 * @method static bool exists()
 */
class {{queryName}} extends BaseModuleQuery
{
    /**
     * Initialize query with model
     *
     * @return static
     */
    public static function init(): static
    {
        $instance = new static();
        $instance->query = {{modelName}}::query();
        return $instance;
    }

    /**
     * Filter only active records
     *
     * @return static
     */
    public function onlyActive(): static
    {
        $this->query->where('is_active', true);
        return $this;
    }

    /**
     * Filter by status
     *
     * @param string $status
     * @return static
     */
    public function byStatus(string $status): static
    {
        $this->query->where('status', $status);
        return $this;
    }

    /**
     * Search in multiple fields
     *
     * @param string $term
     * @return static
     */
    public function search(string $term): static
    {
        $this->query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
        return $this;
    }

    /**
     * Filter by date range
     *
     * @param string $from
     * @param string $to
     * @param string $column
     * @return static
     */
    public function dateRange(string $from, string $to, string $column = 'created_at'): static
    {
        $this->query->whereBetween($column, [$from, $to]);
        return $this;
    }

    /**
     * Get records from today
     *
     * @return static
     */
    public function today(): static
    {
        $this->query->whereDate('created_at', today());
        return $this;
    }

    /**
     * Get records from this week
     *
     * @return static
     */
    public function thisWeek(): static
    {
        $this->query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
        return $this;
    }

    /**
     * Get records from this month
     *
     * @return static
     */
    public function thisMonth(): static
    {
        $this->query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
        return $this;
    }

    // Add your custom query methods here...
}

STUB;
    }


    protected function getBaseModuleQueryStub(): string
    {
        return <<<'STUB'
<?php

namespace App\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Pagination\CursorPaginator;
/**
 * Base Query Builder for Modular Architecture
 *
 * This class provides a fluent, chainable interface for building database queries
 * with features like dynamic eager loading, filtering, sorting, caching, and pagination.
 */
abstract class BaseModuleQuery
{
    protected Builder $query;
    protected static ?self $instance = null;

    /**
     * Initialize query with model
     *
     * @return static
     */
    abstract public static function init(): static;

    /**
     * Get singleton instance
     *
     * @return static
     */
    protected static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = static::init();
        }
        return static::$instance;
    }

    /**
     * Reset instance for new query
     *
     * @return void
     */
    protected static function resetInstance(): void
    {
        static::$instance = null;
    }

    /**
     * Magic static call handler for fluent interface
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = static::getInstance();

        if (method_exists($instance, $method)) {
            $result = $instance->$method(...$parameters);

            // Reset instance after terminal operations
            if (in_array($method, ['get', 'first', 'paginate', 'count', 'exists', 'pluck', 'find'])) {
                static::resetInstance();
            }

            return $result;
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    /**
     * Dynamic eager loading with whitelist
     *
     * @param Request $request
     * @param array $allowed Whitelist of allowed relations
     * @return static
     */
    public function withRelations(Request $request, array $allowed = []): static
    {
        $relations = $request->input('with', []);

        if (is_string($relations)) {
            $relations = explode(',', $relations);
        }

        if (!empty($allowed) && !empty($relations)) {
            $relations = array_intersect($relations, $allowed);
        }

        if (!empty($relations)) {
            $this->query->with($relations);
        }

        return $this;
    }

    /**
     * Apply generic filters from request
     *
     * Supports:
     * - Exact match: field=value
     * - Array: field[]=value1&field[]=value2
     * - Range: field_from=x&field_to=y
     * - Like: field_like=term or search_field=term
     *
     * @param Request $request
     * @return static
     */
public function filter(Request $request): static
{
    $filters = $request->except(['page', 'per_page', 'sort', 'order', 'with', 'cursor']);

    $allowedColumns = array_merge(
        $this->query->getModel()->getFillable(),
        ['id', 'created_at', 'updated_at']
    );

    foreach ($filters as $key => $value) {
        if (is_null($value) || $value === '') {
            continue;
        }

        $column = $key;
        if (str_contains($key, '_from') || str_contains($key, '_start')) {
            $column = str_replace(['_from', '_start'], '', $key);
        } elseif (str_contains($key, '_to') || str_contains($key, '_end')) {
            $column = str_replace(['_to', '_end'], '', $key);
        } elseif (str_contains($key, '_like') || str_contains($key, 'search')) {
            $column = str_replace(['_like', 'search_'], '', $key);
        }

        if (! in_array($column, $allowedColumns)) {
            continue;
        }

        if (is_array($value)) {
            $this->query->whereIn($key, $value);
        } elseif (str_contains($key, '_from') || str_contains($key, '_start')) {
            $this->query->where($column, '>=', $value);
        } elseif (str_contains($key, '_to') || str_contains($key, '_end')) {
            $this->query->where($column, '<=', $value);
        } elseif (str_contains($key, '_like') || str_contains($key, 'search')) {
            $this->query->where($column, 'like', "%{$value}%");
        } else {
            $this->query->where($key, $value);
        }
    }

    return $this;
}

    /**
     * Sort results
     *
     * @param Request $request
     * @param string $default Default sort column
     * @return static
     */
    public function sort(Request $request, string $default = 'id'): static
    {
        $allowedColumns = array_merge(
            $this->query->getModel()->getFillable(),
            ['id', 'created_at', 'updated_at']
        );
        $sortBy = in_array($request->input('sort', $default), $allowedColumns) ? $request->input('sort', $default) : 'id';
        $order = $request->input('order', 'desc');

        if (!in_array(strtolower($order), ['asc', 'desc'])) {
            $order = 'asc';
        }

        $this->query->orderBy($sortBy, $order);

        return $this;
    }

    /**
     * Paginate with cursor
     *
     * @param int $perPage
     * @return CursorPaginator
     */
    public function paginate(int $perPage = 15): CursorPaginator
    {
        return $this->query->cursorPaginate($perPage);
    }

    /**
     * Cache query results
     *
     * @param string $key Cache key
     * @param int $seconds TTL in seconds
     * @return static
     */
    public function cache(string $key, int $seconds = 3600): static
    {
        $cacheKey = $this->generateCacheKey($key);

        // Store query in cache for later execution
        $sql = $this->query->toSql();
        $bindings = $this->query->getBindings();

        Cache::remember($cacheKey, $seconds, function () {
            return [
                'sql' => $this->query->toSql(),
                'bindings' => $this->query->getBindings(),
            ];
        });

        return $this;
    }

    /**
     * Get direct access to query builder
     *
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get results
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get()
    {
        return $this->query->get();
    }

    /**
     * Get first result
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function first()
    {
        return $this->query->first();
    }

    /**
     * Find by ID
     *
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function find($id)
    {
        return $this->query->find($id);
    }

    /**
     * Count results
     *
     * @return int
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Check if results exist
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * Pluck specific column
     *
     * @param string $column
     * @param string|null $key
     * @return \Illuminate\Support\Collection
     */
    public function pluck(string $column, ?string $key = null)
    {
        return $this->query->pluck($column, $key);
    }

    /**
     * Apply where condition
     *
     * @param mixed $column
     * @param mixed $operator
     * @param mixed $value
     * @return static
     */
    public function where($column, $operator = null, $value = null): static
    {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    /**
     * Apply whereIn condition
     *
     * @param string $column
     * @param array $values
     * @return static
     */
    public function whereIn(string $column, array $values): static
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    /**
     * Apply whereNull condition
     *
     * @param string $column
     * @return static
     */
    public function whereNull(string $column): static
    {
        $this->query->whereNull($column);
        return $this;
    }

    /**
     * Apply whereNotNull condition
     *
     * @param string $column
     * @return static
     */
    public function whereNotNull(string $column): static
    {
        $this->query->whereNotNull($column);
        return $this;
    }

    /**
     * Apply limit
     *
     * @param int $limit
     * @return static
     */
    public function limit(int $limit): static
    {
        $this->query->limit($limit);
        return $this;
    }

    /**
     * Apply offset
     *
     * @param int $offset
     * @return static
     */
    public function offset(int $offset): static
    {
        $this->query->offset($offset);
        return $this;
    }

    /**
     * Generate cache key
     *
     * @param string $key
     * @return string
     */
    protected function generateCacheKey(string $key): string
    {
        $sql = $this->query->toSql();
        $bindings = serialize($this->query->getBindings());

        return sprintf(
            '%s:%s',
            $key,
            md5($sql . $bindings)
        );
    }

    /**
     * Clear cache for specific key pattern
     *
     * @param string $pattern
     * @return void
     */
    public static function clearCache(string $pattern = '*'): void
    {
        try {
            $keys = Cache::getRedis()->keys($pattern);

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        } catch (\Exception $e) {
            // Handle case where Redis is not available
            Cache::flush();
        }
    }
        public function cachedPaginate(
        Request $request,
        int $perPage = 15,
        int $ttlMinutes = 30,
        array $extraTags = []
    ): array
    {
        return \App\Support\Cache\CacheService::rememberList(
            $this->query,
            $request,
            fn () => $this->paginate($perPage)->toArray(),
            $ttlMinutes,
            $extraTags
        );
    }
}

STUB;
    }
}
