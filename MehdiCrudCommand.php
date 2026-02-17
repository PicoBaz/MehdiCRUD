<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Symfony\Component\VarExporter\VarExporter;

class MehdiCrudCommand extends Command
{
    protected $signature = 'mehdi:crud {model : The model name} {module? : The module name}';

    protected $description = 'Generate CRUD Request classes, Query class and Controller based on migration fields';

    protected Filesystem $filesystem;

    private bool $isModular = false;
    private string $moduleName = '';
    private string $modulePath = '';
    private string $modelName = '';
    private string $tableName = '';
    private array $migrationColumns = [];

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    public function handle()
    {
        try {
            $this->modelName = $this->argument('model');
            $this->moduleName = $this->argument('module');
            $this->error($this->moduleName);
            $this->isModular = !empty($this->moduleName);
            if (!$this->validateModel()) {
                return CommandAlias::FAILURE;
            }



            if ($this->isModular) {
                $this->modulePath = base_path("Modules/{$this->moduleName}");
                if (!$this->filesystem->isDirectory($this->modulePath)) {
                    $this->error("ماژول {$this->moduleName} وجود ندارد");
                    return CommandAlias::FAILURE;
                }
            }

            $this->extractTableNameAndColumns();

            if (empty($this->migrationColumns)) {
                $this->error("هیچ فیلدی در migration پیدا نشد");
                return CommandAlias::FAILURE;
            }

            $this->generateRequestClasses();
            $this->generateQueryClass();
            $this->generateController();

            $this->info("✓ تمام فایل‌ها با موفقیت ایجاد شدند!");
            return CommandAlias::SUCCESS;
        } catch (Exception $e) {
            $this->error("خطا: " . $e->getMessage());
            return CommandAlias::FAILURE;
        }
    }

    private function validateModel(): bool
    {
        $modelClass = $this->getModelClass();
        if (!class_exists($modelClass)) {
            $this->error("مدل {$this->modelName} پیدا نشد");
            return false;
        }

        try {
            $model = app($modelClass);
            if (!method_exists($model, 'getTable')) {
                $this->error("مدل معتبری نیست");
                return false;
            }
        } catch (Exception $e) {
            $this->error("خطا در بارگذاری مدل: " . $e->getMessage());
            return false;
        }

        return true;
    }

    private function getModelClass(): string
    {
        if ($this->isModular) {
            return "Modules\\{$this->moduleName}\\Models\\{$this->modelName}";
        }
        return "App\\Models\\{$this->modelName}";
    }

    private function extractTableNameAndColumns(): void
    {
        $modelClass = $this->getModelClass();
        $model = app($modelClass);
        $this->tableName = $model->getTable();

        $this->migrationColumns = $this->getMigrationColumns();
    }

    private function getMigrationColumns(): array
    {
        $migrationPath = $this->isModular
            ? $this->modulePath . '/database/migrations'
            : database_path('migrations');

        if (!$this->filesystem->isDirectory($migrationPath)) {
            $this->warn("دایرکتوری migrations یافت نشد");
            return [];
        }

        $files = $this->filesystem->files($migrationPath);
        $columns = [];

        foreach ($files as $file) {
            $content = $this->filesystem->get($file->getPathname());

            if (strpos($content, "create('{$this->tableName}'") !== false ||
                strpos($content, 'create("' . $this->tableName . '"') !== false) {
                $columns = $this->parseColumnsFromMigration($content);
                break;
            }
        }

        return $columns;
    }

    private function parseColumnsFromMigration(string $content): array
    {
        $columns = [];
        $pattern = '/\$table\s*->\s*(\w+)\s*\(\s*["\']?(\w+)["\']?[^)]*\)/';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $columnType = $match[1];
            $columnName = $match[2];

            if (in_array($columnName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $columnInfo = [
                'name' => $columnName,
                'type' => $columnType,
                'nullable' => strpos($match[0], '->nullable()') !== false,
                'unique' => strpos($match[0], '->unique()') !== false,
                'default' => $this->extractDefault($match[0]),
                'enum_values' => $this->extractEnumValues($match[0]),
                'length' => $this->extractLength($match[0]),
            ];

            $columns[$columnName] = $columnInfo;
        }

        return $columns;
    }

    private function extractDefault(string $columnDef): ?string
    {
        if (preg_match('/->default\s*\(\s*["\']?(\w+)["\']?\s*\)/', $columnDef, $match)) {
            return $match[1];
        }
        return null;
    }

    private function extractEnumValues(string $columnDef): array
    {
        if (preg_match('/->enum\s*\(\s*["\']?\w+["\']?\s*,\s*\[(.*?)\]/', $columnDef, $match)) {
            preg_match_all('/["\'](\w+)["\']/', $match[1], $values);
            return $values[1] ?? [];
        }
        return [];
    }

    private function extractLength(string $columnDef): ?int
    {
        if (preg_match('/\(\s*(\d+)\s*\)/', $columnDef, $match)) {
            return (int)$match[1];
        }
        return null;
    }

    private function generateRequestClasses(): void
    {
        $requestPath = $this->isModular
            ? "{$this->modulePath}/app/Http/Requests/{$this->modelName}"
            : base_path("app/Http/Requests/{$this->modelName}");

        $this->filesystem->makeDirectory($requestPath, 0755, true, true);

        $this->generateStoreRequest($requestPath);
        $this->generateUpdateRequest($requestPath);

        $this->line("✓ Request کلاس‌ها ایجاد شدند");
    }

    private function generateStoreRequest(string $requestPath): void
    {
        $rules = $this->generateRulesForStore();

        $namespace = $this->isModular
            ? "Modules\\{$this->moduleName}\\Http\\Requests\\{$this->modelName}"
            : "App\\Http\\Requests\\{$this->modelName}";

        $content = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= "use Illuminate\Foundation\Http\FormRequest;\n\n";
        $content .= "class {$this->modelName}StoreRequest extends FormRequest\n";
        $content .= "{\n";
        $content .= "    public function authorize(): bool\n";
        $content .= "    {\n";
        $content .= "        return true;\n";
        $content .= "    }\n\n";
        $content .= "    public function rules(): array\n";
        $content .= "    {\n";
        $content .= "        return " . VarExporter::export($rules) . ";\n";
        $content .= "    }\n";
        $content .= "}\n";

        $this->filesystem->put(
            "{$requestPath}/{$this->modelName}StoreRequest.php",
            $content
        );
    }

    private function generateUpdateRequest(string $requestPath): void
    {
        $rules = $this->generateRulesForUpdate();

        $namespace = $this->isModular
            ? "Modules\\{$this->moduleName}\\Http\\Requests\\{$this->modelName}"
            : "App\\Http\\Requests\\{$this->modelName}";

        $content = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= "use Illuminate\Foundation\Http\FormRequest;\n\n";
        $content .= "class {$this->modelName}UpdateRequest extends FormRequest\n";
        $content .= "{\n";
        $content .= "    public function authorize(): bool\n";
        $content .= "    {\n";
        $content .= "        return true;\n";
        $content .= "    }\n\n";
        $content .= "    public function rules(): array\n";
        $content .= "    {\n";
        $content .= "        return " . VarExporter::export($rules) . ";\n";
        $content .= "    }\n";
        $content .= "}\n";

        $this->filesystem->put(
            "{$requestPath}/{$this->modelName}UpdateRequest.php",
            $content
        );
    }

    private function generateRulesForStore(): array
    {
        $rules = [];

        foreach ($this->migrationColumns as $column) {
            $columnRules = $this->buildRulesForColumn($column, false);
            if (!empty($columnRules)) {
                $rules[$column['name']] = $columnRules;
            }
        }

        return $rules;
    }

    private function generateRulesForUpdate(): array
    {
        $rules = [];

        foreach ($this->migrationColumns as $column) {
            $columnRules = $this->buildRulesForColumn($column, true);
            if (!empty($columnRules)) {
                $rules[$column['name']] = $columnRules;
            }
        }

        if (isset($this->migrationColumns['is_active'])) {
            $rules['is_active'] = 'nullable|in:' . implode(',', $this->migrationColumns['is_active']['enum_values'] ?? []);
        }

        return $rules;
    }

    private function buildRulesForColumn(array $column, bool $isUpdate): string
    {
        $rules = [];

        if (!$isUpdate && !$column['nullable']) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $columnType = $column['type'];

        if ($columnType === 'enum' && !empty($column['enum_values'])) {
            $rules[] = 'in:' . implode(',', $column['enum_values']);
            return implode('|', $rules);
        }

        if (in_array($columnType, ['string', 'varchar', 'text', 'longText', 'mediumText'])) {
            $rules[] = 'string';
        } elseif (in_array($columnType, ['integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'unsignedInteger'])) {
            $rules[] = 'integer';
        } elseif (in_array($columnType, ['decimal', 'double', 'float'])) {
            $rules[] = 'numeric';
        } elseif ($columnType === 'boolean') {
            $rules[] = 'boolean';
        } elseif ($columnType === 'date') {
            $rules[] = 'date';
        } elseif ($columnType === 'dateTime' || $columnType === 'timestamp') {
            $rules[] = 'date_format:Y-m-d H:i:s';
        }

        if ($column['unique']) {
            $rules[] = "unique:{$this->tableName},{$column['name']}";
        }

        return implode('|', $rules);
    }

    private function generateQueryClass(): void
    {
        if ($this->isModular) {
            $this->call('module:make-query', [
                'model' => $this->modelName,
                'name' => $this->modelName . 'Query',
                'module' => $this->moduleName
            ]);
        } else {
            $this->call('make:query', [
                'name' => $this->modelName . 'Query'
            ]);
        }

        $this->line("✓ Query کلاس ایجاد شد");
    }

    private function generateController(): void
    {
        $controllerPath = $this->isModular
            ? "{$this->modulePath}/app/Http/Controllers/{$this->modelName}"
            : base_path("app/Http/Controllers/{$this->modelName}");

        $this->filesystem->makeDirectory($controllerPath, 0755, true, true);

        $controllerContent = $this->buildControllerContent();

        $this->filesystem->put(
            "{$controllerPath}/{$this->modelName}Controller.php",
            $controllerContent
        );

        $this->line("✓ Controller ایجاد شد");
    }

    private function buildControllerContent(): string
    {
        $namespace = $this->isModular
            ? "Modules\\{$this->moduleName}\\Http\\Controllers\\{$this->modelName}"
            : "App\\Http\\Controllers\\{$this->modelName}";

        $requestNamespace = $this->isModular
            ? "Modules\\{$this->moduleName}\\Http\\Requests\\{$this->modelName}"
            : "App\\Http\\Requests\\{$this->modelName}";

        $modelNamespace = $this->isModular
            ? "Modules\\{$this->moduleName}\\Models\\{$this->modelName}"
            : "App\\Models\\{$this->modelName}";

        $queryNamespace = $this->isModular
            ? "Modules\\{$this->moduleName}\\Queries\\{$this->modelName}Query"
            : "App\\Queries\\{$this->modelName}Query";

        $modelVariable = lcfirst($this->modelName);
        $modelVariablePlural = Str::plural($modelVariable);

        $content = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= "use App\Http\Controllers\Controller;\n";
        $content .= "use Illuminate\Http\Request;\n";
        $content .= "use {$requestNamespace}\\{$this->modelName}StoreRequest;\n";
        $content .= "use {$requestNamespace}\\{$this->modelName}UpdateRequest;\n";
        $content .= "use {$modelNamespace};\n";
        $content .= "use {$queryNamespace};\n\n";
        $content .= "class {$this->modelName}Controller extends Controller\n";
        $content .= "{\n";

        $content .= "    public function index(Request \$request): \\Illuminate\\Http\\JsonResponse\n";
        $content .= "    {\n";
        $content .= "        \${$modelVariablePlural} = {$this->modelName}Query::init()\n";
        $content .= "            ->sort(\$request)\n";
        $content .= "            ->filter(\$request)\n";
        $content .= "            ->withRelations(\$request, [])\n";
        $content .= "            ->cachedPaginate(\$request, perPage: 15, ttlMinutes: 45, extraTags: ['active-']);\n\n";
        $content .= "        return myResponse(__('messages.success'), 200, compact('{$modelVariablePlural}'));\n";
        $content .= "    }\n\n";

        $content .= "    public function store({$this->modelName}StoreRequest \$request): \\Illuminate\\Http\\JsonResponse\n";
        $content .= "    {\n";
        $content .= "        \${$modelVariable} = {$this->modelName}::create(\$request->validated());\n\n";
        $content .= "        return myResponse(__('messages.success'), 200, compact('{$modelVariable}'));\n";
        $content .= "    }\n\n";

        $content .= "    public function show({$this->modelName} \${$modelVariable}): \\Illuminate\\Http\\JsonResponse\n";
        $content .= "    {\n";
        $content .= "        return myResponse(__('messages.success'), 200, compact('{$modelVariable}'));\n";
        $content .= "    }\n\n";

        $content .= "    public function update({$this->modelName}UpdateRequest \$request, {$this->modelName} \${$modelVariable}): \\Illuminate\\Http\\JsonResponse\n";
        $content .= "    {\n";
        $content .= "        \${$modelVariable}->update(\$request->validated());\n\n";
        $content .= "        return myResponse(__('messages.success'), 200, compact('{$modelVariable}'));\n";
        $content .= "    }\n\n";

        $content .= "    public function destroy({$this->modelName} \${$modelVariable}): \\Illuminate\\Http\\JsonResponse\n";
        $content .= "    {\n";
        $content .= "        \${$modelVariable}->delete();\n\n";
        $content .= "        return myResponse(__('messages.success'));\n";
        $content .= "    }\n";

        $content .= "}\n";

        return $content;
    }
}
