<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateCrud extends Command
{
    protected $signature = 'create:crud {entity} {--fields=}';
    protected $description = 'Create a complete CRUD for an entity with React components';

    protected $entityName;
    protected $entityLower;
    protected $entityPlural;
    protected $entityPluralLower;
    protected $fields = [];

    protected $availableFieldTypes = [
        'string', 'integer', 'decimal', 'boolean', 'date', 'text', 
        'longtext', 'email', 'file', 'url'
    ];

    public function handle()
    {
        $this->entityName = ucfirst($this->argument('entity'));
        $this->entityLower = strtolower($this->entityName);
        $this->entityPlural = Str::plural($this->entityName);
        $this->entityPluralLower = strtolower($this->entityPlural);

        $this->info("Creating CRUD for {$this->entityName}...");

        // Get fields from user
        $fieldsInput = $this->option('fields');
        if (!$fieldsInput) {
            $this->fields = $this->askForFields();
        } else {
            $this->fields = $this->parseFields($fieldsInput);
        }

        // Generate all components
        $this->generateModel();
        $this->generateMigration();
        $this->generateController();
        $this->generateReactComponents();
        $this->addWebRoutes();
        $this->updateNavigation();

        // Run migration if table doesn't exist
        $this->runMigrationIfTableDoesNotExist();

        $this->info("CRUD for {$this->entityName} created successfully!");
        $this->info("Don't forget to run: php artisan storage:link");
    }

    protected function askForFields()
    {
        $fields = [];
        $this->info("Enter fields for {$this->entityName} (press Enter when done):");

        while (true) {
            $fieldName = $this->ask("Field name (or press Enter to finish)");
            if (empty($fieldName)) {
                break;
            }

            $fieldType = $this->askWithMenu($this->availableFieldTypes, "Field type for '{$fieldName}'");

            $fields[] = [
                'name' => $fieldName,
                'type' => $fieldType,
            ];
        }

        return $fields;
    }

    protected function parseFields($fieldsString)
    {
        $fields = [];
        $fieldPairs = explode(',', $fieldsString);

        foreach ($fieldPairs as $pair) {
            $parts = explode(':', trim($pair));
            if (count($parts) === 2) {
                $fields[] = [
                    'name' => trim($parts[0]),
                    'type' => trim($parts[1]),
                ];
            }
        }

        return $fields;
    }

    protected function runMigrationIfTableDoesNotExist()
    {
        if (!Schema::hasTable($this->entityPluralLower)) {
            $this->call('migrate');
            $this->info("Migration executed successfully.");
        } else {
            $this->info("Table '{$this->entityPluralLower}' already exists. Skipping migration.");
        }
    }

    protected function generateModel()
    {
        $fillableFields = implode(",\n        ", array_map(function ($field) {
            return "'{$field['name']}'";
        }, $this->fields));

        // Generate casts for date fields
        $dateFields = array_filter($this->fields, function ($field) {
            return $field['type'] === 'date';
        });
        
        $dateCasts = '';
        if (!empty($dateFields)) {
            $castFields = implode(",\n        ", array_map(function ($field) {
                return "'{$field['name']}' => 'date'";
            }, $dateFields));
            $dateCasts = "

    protected \$casts = [
        {$castFields},
    ];";
        }

        $template = File::get(resource_path('templates/model.stub'));
        $content = str_replace([
            '{{entity}}',
            '{{entityPluralLower}}',
            '{{fieldsFillable}}',
            '{{dateCasts}}'
        ], [
            $this->entityName,
            $this->entityPluralLower,
            $fillableFields,
            $dateCasts
        ], $template);

        $modelPath = app_path("Models/{$this->entityName}.php");
        File::put($modelPath, $content);
        $this->info("Model created: {$modelPath}");
    }

    protected function generateMigration()
    {
        $migrationFields = implode("\n            ", array_map(function ($field) {
            $type = $field['type'];
            $name = $field['name'];
            
            switch ($type) {
                case 'string':
                    return "\$table->string('{$name}');";
                case 'integer':
                    return "\$table->integer('{$name}');";
                case 'decimal':
                    return "\$table->decimal('{$name}', 10, 2);";
                case 'boolean':
                    return "\$table->boolean('{$name}')->default(false);";
                case 'date':
                    return "\$table->date('{$name}');";
                case 'text':
                    return "\$table->text('{$name}')->nullable();";
                case 'longtext':
                    return "\$table->longText('{$name}')->nullable();";
                case 'email':
                    return "\$table->string('{$name}');";
                case 'file':
                    return "\$table->string('{$name}')->nullable();";
                case 'url':
                    return "\$table->string('{$name}')->nullable();";
                default:
                    return "\$table->string('{$name}');";
            }
        }, $this->fields));

        $template = File::get(resource_path('templates/migration.stub'));
        $content = str_replace([
            '{{entityPlural}}',
            '{{migrationFields}}'
        ], [
            $this->entityPluralLower,
            $migrationFields
        ], $template);

        $migrationFileName = now()->format('Y_m_d_His') . "_create_{$this->entityPluralLower}_table.php";
        $migrationPath = database_path("migrations/{$migrationFileName}");
        File::put($migrationPath, $content);
        $this->info("Migration created: {$migrationPath}");
    }

    protected function generateController()
    {
        $validationRules = implode(",\n            ", array_map(function ($field) {
            $rules = [];
            
            switch ($field['type']) {
                case 'string':
                case 'text':
                case 'longtext':
                    $rules[] = 'nullable|string';
                    break;
                case 'integer':
                    $rules[] = 'nullable|integer';
                    break;
                case 'decimal':
                    $rules[] = 'nullable|numeric';
                    break;
                case 'boolean':
                    $rules[] = 'nullable|boolean';
                    break;
                case 'date':
                    $rules[] = 'nullable|date';
                    break;
                case 'email':
                    $rules[] = 'nullable|email';
                    break;
                case 'file':
                    $rules[] = 'nullable|file';
                    break;
                case 'url':
                    $rules[] = 'nullable|url';
                    break;
                default:
                    $rules[] = 'nullable|string';
            }
            
            return "'{$field['name']}' => '" . implode('|', $rules) . "'";
        }, $this->fields));

        // Generate file upload handling code
        $fileUploadCode = '';
        $dateFormatCode = '';
        foreach ($this->fields as $field) {
            if ($field['type'] === 'file') {
                $fileUploadCode .= "
                // Handle {$field['name']} file upload
                if (\$request->hasFile('{$field['name']}')) {
                    \$file = \$request->file('{$field['name']}');
                    \$fileName = time() . '_' . \$file->getClientOriginalName();
                    \$file->storeAs('{$this->entityPluralLower}', \$fileName, 'public');
                    \$data['{$field['name']}'] = \$fileName;
                }";
            }
            if ($field['type'] === 'date') {
                $dateFormatCode .= "
                // Format {$field['name']} to Y-m-d format for database
                if (isset(\$data['{$field['name']}']) && \$data['{$field['name']}']) {
                    \$data['{$field['name']}'] = date('Y-m-d', strtotime(\$data['{$field['name']}']));
                }";
            }
        }

        $template = File::get(resource_path('templates/controller.stub'));
        $content = str_replace([
            '{{entity}}',
            '{{entityLower}}',
            '{{entityPlural}}',
            '{{entityPluralLower}}',
            '{{fieldsValidation}}',
            '{{fileUploadCode}}',
            '{{dateFormatCode}}'
        ], [
            $this->entityName,
            $this->entityLower,
            $this->entityPlural,
            $this->entityPluralLower,
            $validationRules,
            $fileUploadCode,
            $dateFormatCode
        ], $template);

        $controllerPath = app_path("Http/Controllers/{$this->entityName}Controller.php");
        File::put($controllerPath, $content);
        $this->info("Controller created: {$controllerPath}");
    }

    protected function generateReactComponents()
    {
        $componentsDir = resource_path("js/Pages/{$this->entityPlural}");
        if (!File::exists($componentsDir)) {
            File::makeDirectory($componentsDir, 0755, true);
        }

        // Generate Index component
        $this->generateIndexComponent();
        
        // Generate Create component
        $this->generateCreateComponent();
        
        // Generate Edit component
        $this->generateEditComponent();
        
        // Generate Show component
        $this->generateShowComponent();
    }

    protected function generateIndexComponent()
    {
        $tableHeaders = implode("\n                                            ", array_map(function ($field) {
            return "<th className=\"px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">{$field['name']}</th>";
        }, $this->fields));

        $tableCells = implode("\n                                                ", array_map(function ($field) {
            return "<td className=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\">\n                                                    {{$this->entityLower}.{$field['name']}}\n                                                </td>";
        }, $this->fields));

        $indexContent = "import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ auth, {$this->entityPluralLower} }) {
    return (
        <AuthenticatedLayout
            header=\"{$this->entityPlural}\"
            breadcrumbs={[
                { label: 'Dashboard', href: route('dashboard') },
                { label: '{$this->entityPlural}', href: route('{$this->entityPluralLower}.index') }
            ]}
        >
            <Head title=\"{$this->entityPlural}\" />

            <div className=\"bg-white shadow-sm rounded-lg p-6\">
                <div className=\"flex justify-between items-center mb-6\">
                    <h3 className=\"text-lg font-semibold\">{$this->entityName} Management</h3>
                    <Link href={route('{$this->entityPluralLower}.create')} className=\"bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded\">
                        Add {$this->entityName}
                    </Link>
                </div>

                <div className=\"overflow-x-auto\">
                    <table className=\"min-w-full divide-y divide-gray-200\">
                        <thead className=\"bg-gray-50\">
                            <tr>
                                {$tableHeaders}
                                <th className=\"px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">Actions</th>
                            </tr>
                        </thead>
                        <tbody className=\"bg-white divide-y divide-gray-200\">
                            {{$this->entityPluralLower}.data.map(({$this->entityLower}) => (
                                <tr key={{$this->entityLower}.id}>
                                    {$tableCells}
                                    <td className=\"px-6 py-4 whitespace-nowrap text-sm font-medium\">
                                        <div className=\"flex space-x-2\">
                                            <Link href={route('{$this->entityPluralLower}.show', {$this->entityLower}.id)} className=\"text-blue-600 hover:text-blue-900\">
                                                View
                                            </Link>
                                            <Link href={route('{$this->entityPluralLower}.edit', {$this->entityLower}.id)} className=\"text-indigo-600 hover:text-indigo-900\">
                                                Edit
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}";

        $indexPath = resource_path("js/Pages/{$this->entityPlural}/Index.jsx");
        File::put($indexPath, $indexContent);
        $this->info("Index component created: {$indexPath}");
    }

    protected function generateCreateComponent()
    {
        $formFields = implode("\n                                ", array_map(function ($field) {
            $inputType = $this->getInputType($field['type']);
            $isTextarea = $field['type'] === 'text' || $field['type'] === 'longtext';
            
            if ($isTextarea) {
                $rows = $field['type'] === 'longtext' ? '6' : '3';
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <textarea
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        value={data.{$field['name']}}
                                        onChange={(e) => setData('{$field['name']}', e.target.value)}
                                        rows=\"{$rows}\"
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
                                </div>";
            } elseif ($field['type'] === 'file') {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <input
                                        type=\"file\"
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        onChange={(e) => setData('{$field['name']}', e.target.files[0])}
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
                                </div>";
            } elseif ($field['type'] === 'date') {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <DatePicker
                                        value={data.{$field['name']}}
                                        onChange={(date) => setData('{$field['name']}', date)}
                                        placeholder=\"Select {$field['name']}\"
                                        error={errors.{$field['name']}}
                                    />
                                </div>";
            } else {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <input
                                        type=\"{$inputType}\"
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        value={data.{$field['name']}}
                                        onChange={(e) => setData('{$field['name']}', e.target.value)}
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
                                </div>";
            }
        }, $this->fields));

        $formData = implode(",\n        ", array_map(function ($field) {
            if ($field['type'] === 'file') {
                return "{$field['name']}: null";
            } elseif ($field['type'] === 'date') {
                return "{$field['name']}: null";
            } elseif ($field['type'] === 'boolean') {
                return "{$field['name']}: false";
            }
            return "{$field['name']}: ''";
        }, $this->fields));

        // Check if we have date fields for conditional imports
        $hasDateFields = !empty(array_filter($this->fields, function ($field) {
            return $field['type'] === 'date';
        }));

        // Check if we have boolean fields for conditional imports
        $hasBooleanFields = !empty(array_filter($this->fields, function ($field) {
            return $field['type'] === 'boolean';
        }));

        $datePickerImport = $hasDateFields ? "import DatePicker from '@/Components/ui/DatePicker';" : "";
        $switchImport = $hasBooleanFields ? "import { Switch } from '@/Components/ui/ui/switch';" : "";

        $createContent = "import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
{$datePickerImport}
{$switchImport}

export default function Create({ auth, errors }) {
    const { data, setData, post, processing } = useForm({
        {$formData},
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('{$this->entityPluralLower}.store'), {
            preserveScroll: true,
            onSuccess: () => {
                // Form will be reset automatically
            },
        });
    };

    return (
        <AuthenticatedLayout
            header=\"Create {$this->entityName}\"
            breadcrumbs={[
                { label: 'Dashboard', href: route('dashboard') },
                { label: '{$this->entityPlural}', href: route('{$this->entityPluralLower}.index') },
                { label: 'Create {$this->entityName}', href: route('{$this->entityPluralLower}.create') }
            ]}
        >
            <Head title=\"Create {$this->entityName}\" />

            <div className=\"bg-white shadow-sm rounded-lg p-6\">
                <form onSubmit={submit}>
                                {$formFields}

                    <div className=\"flex items-center justify-between\">
                        <button
                            type=\"submit\"
                            disabled={processing}
                            className=\"bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline\"
                        >
                            Create {$this->entityName}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}";

        $createPath = resource_path("js/Pages/{$this->entityPlural}/Create.jsx");
        File::put($createPath, $createContent);
        $this->info("Create component created: {$createPath}");
    }

    protected function generateEditComponent()
    {
        $formFields = implode("\n                                ", array_map(function ($field) {
            $inputType = $this->getInputType($field['type']);
            $isTextarea = $field['type'] === 'text' || $field['type'] === 'longtext';
            
            if ($isTextarea) {
                $rows = $field['type'] === 'longtext' ? '6' : '3';
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <textarea
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        value={data.{$field['name']}}
                                        onChange={(e) => setData('{$field['name']}', e.target.value)}
                                        rows=\"{$rows}\"
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
                                </div>";
            } elseif ($field['type'] === 'file') {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <input
                                        type=\"file\"
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        onChange={(e) => setData('{$field['name']}', e.target.files[0])}
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
                                </div>";
            } elseif ($field['type'] === 'date') {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <DatePicker
                                        value={data.{$field['name']}}
                                        onChange={(date) => setData('{$field['name']}', date)}
                                        placeholder=\"Select {$field['name']}\"
                                        error={errors.{$field['name']}}
                                    />
                                </div>";
            } else {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <input
                                        type=\"{$inputType}\"
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        value={data.{$field['name']}}
                                        onChange={(e) => setData('{$field['name']}', e.target.value)}
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
                                </div>";
            }
        }, $this->fields));

        $formData = implode(",\n        ", array_map(function ($field) {
            if ($field['type'] === 'file') {
                return "{$field['name']}: null";
            } elseif ($field['type'] === 'date') {
                return "{$field['name']}: {$this->entityLower}.{$field['name']} || null";
            }
            return "{$field['name']}: {$this->entityLower}.{$field['name']} || ''";
        }, $this->fields));

        // Check if we have date fields for conditional imports
        $hasDateFields = !empty(array_filter($this->fields, function ($field) {
            return $field['type'] === 'date';
        }));

        $datePickerImport = $hasDateFields ? "import DatePicker from '@/Components/ui/DatePicker';" : "";

        $editContent = "import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
{$datePickerImport}

export default function Edit({ auth, {$this->entityLower}, errors }) {
    const { data, setData, put, processing } = useForm({
        {$formData},
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('{$this->entityPluralLower}.update', {$this->entityLower}.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Form will be reset automatically
            },
        });
    };

    return (
        <AuthenticatedLayout
            header=\"Edit {$this->entityName}\"
            breadcrumbs={[
                { label: 'Dashboard', href: route('dashboard') },
                { label: '{$this->entityPlural}', href: route('{$this->entityPluralLower}.index') },
                { label: 'Edit {$this->entityName}', href: route('{$this->entityPluralLower}.edit', {$this->entityLower}.id) }
            ]}
        >
            <Head title=\"Edit {$this->entityName}\" />

            <div className=\"bg-white shadow-sm rounded-lg p-6\">
                <form onSubmit={submit}>
                    {$formFields}

                    <div className=\"flex items-center justify-between\">
                        <button
                            type=\"submit\"
                            disabled={processing}
                            className=\"bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline\"
                        >
                            Update {$this->entityName}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}";

        $editPath = resource_path("js/Pages/{$this->entityPlural}/Edit.jsx");
        File::put($editPath, $editContent);
        $this->info("Edit component created: {$editPath}");
    }

    protected function generateShowComponent()
    {
        $showFields = implode("\n                                    ", array_map(function ($field) {
            if ($field['type'] === 'file') {
                return "<div>
                                        <label className=\"block text-sm font-medium text-gray-700\">{$field['name']}</label>
                                        <div className=\"mt-1\">
                                            {{$this->entityLower}.{$field['name']} ? (
                                                <div>
                                                    {{$this->entityLower}.{$field['name']}.match(/\\.(jpg|jpeg|png|gif|webp)$/i) ? (
                                                        <img 
                                                            src={\"/storage/{$this->entityPluralLower}/\" + {$this->entityLower}.{$field['name']}} 
                                                            alt=\"{$field['name']}\" 
                                                            className=\"max-w-xs rounded shadow-sm\"
                                                        />
                                                    ) : (
                                                        <a 
                                                            href={\"/storage/{$this->entityPluralLower}/\" + {$this->entityLower}.{$field['name']}} 
                                                            download
                                                            className=\"text-blue-600 hover:text-blue-800 underline\"
                                                        >
                                                            Download {$field['name']}
                                                        </a>
                                                    )}
                                                </div>
                                            ) : (
                                                <p className=\"text-gray-500\">No file uploaded</p>
                                            )}
                                        </div>
                                    </div>";
            } elseif ($field['type'] === 'date') {
                return "<div>
                                        <label className=\"block text-sm font-medium text-gray-700\">{$field['name']}</label>
                                        <p className=\"mt-1 text-sm text-gray-900\">
                                            {{$this->entityLower}.{$field['name']} ? new Date({$this->entityLower}.{$field['name']}).toLocaleDateString() : 'Not set'}
                                        </p>
                                    </div>";
            } elseif ($field['type'] === 'url') {
                return "<div>
                                        <label className=\"block text-sm font-medium text-gray-700\">{$field['name']}</label>
                                        <p className=\"mt-1 text-sm text-gray-900\">
                                            {{$this->entityLower}.{$field['name']} ? (
                                                <a href={{{$this->entityLower}.{$field['name']}}} target=\"_blank\" rel=\"noopener noreferrer\" className=\"text-blue-600 hover:text-blue-800 underline\">
                                                    {{$this->entityLower}.{$field['name']}}
                                                </a>
                                            ) : 'Not set'}
                                        </p>
                                    </div>";
            } else {
                return "<div>
                                        <label className=\"block text-sm font-medium text-gray-700\">{$field['name']}</label>
                                        <p className=\"mt-1 text-sm text-gray-900\">{{$this->entityLower}.{$field['name']}}</p>
                                    </div>";
            }
        }, $this->fields));

        $showContent = "import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ auth, {$this->entityLower} }) {
    return (
        <AuthenticatedLayout
            header=\"{$this->entityName} Details\"
            breadcrumbs={[
                { label: 'Dashboard', href: route('dashboard') },
                { label: '{$this->entityPlural}', href: route('{$this->entityPluralLower}.index') },
                { label: '{$this->entityName} Details', href: route('{$this->entityPluralLower}.show', {$this->entityLower}.id) }
            ]}
        >
            <Head title=\"{$this->entityName} Details\" />

            <div className=\"bg-white shadow-sm rounded-lg p-6\">
                <div className=\"mb-6\">
                    <h3 className=\"text-lg font-semibold mb-4\">{$this->entityName} Information</h3>
                    
                    <div className=\"grid grid-cols-1 md:grid-cols-2 gap-4\">
                        {$showFields}
                    </div>
                </div>

                <div className=\"flex space-x-4\">
                    <Link
                        href={route('{$this->entityPluralLower}.edit', {$this->entityLower}.id)}
                        className=\"bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded\"
                    >
                        Edit {$this->entityName}
                    </Link>
                    <Link
                        href={route('{$this->entityPluralLower}.index')}
                        className=\"bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded\"
                    >
                        Back to List
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}";

        $showPath = resource_path("js/Pages/{$this->entityPlural}/Show.jsx");
        File::put($showPath, $showContent);
        $this->info("Show component created: {$showPath}");
    }



    protected function getInputType($fieldType)
    {
        switch ($fieldType) {
            case 'email':
                return 'email';
            case 'integer':
            case 'decimal':
                return 'number';
            case 'url':
                return 'url';
            case 'boolean':
                return 'checkbox';
            default:
                return 'text';
        }
    }

    protected function addWebRoutes()
    {
        $routesPath = base_path('routes/web.php');
        $routes = File::get($routesPath);

        // Add use statement if it doesn't exist
        $useStatement = "use App\\Http\\Controllers\\{$this->entityName}Controller;";
        if (!str_contains($routes, $useStatement)) {
            // Find the last use statement and add after it
            $lines = explode("\n", $routes);
            $newLines = [];
            $useAdded = false;
            
            foreach ($lines as $line) {
                $newLines[] = $line;
                // Add the new use statement after the last existing use statement
                if (str_contains($line, 'use App\\Http\\Controllers\\') && !$useAdded) {
                    $newLines[] = $useStatement;
                    $useAdded = true;
                }
            }
            
            $routes = implode("\n", $newLines);
        }

        $newRoutes = "
    Route::resource('{$this->entityPluralLower}', {$this->entityName}Controller::class);";

        if (!str_contains($routes, $newRoutes)) {
            // Find the auth middleware group and add the route inside it
            $pattern = '/(Route::middleware\(\'auth\'\)->group\(function \(\) \{)(.*?)(\}\);)/s';
            $replacement = '$1$2' . $newRoutes . '$3';
            
            $newContent = preg_replace($pattern, $replacement, $routes);
            
            if ($newContent !== $routes) {
                File::put($routesPath, $newContent);
                $this->info("Routes added to web.php");
            } else {
                // Fallback: add at the end
                $routes .= $newRoutes;
                File::put($routesPath, $routes);
                $this->info("Routes added to web.php (fallback)");
            }
        }
    }

    protected function updateNavigation()
    {
        $sidebarPath = resource_path('js/Components/AppSidebar.jsx');
        if (File::exists($sidebarPath)) {
            $sidebar = File::get($sidebarPath);
            
            // Check if navigation item already exists
            $sidebarItem = "              <SidebarMenuItem>
                <SidebarMenuButton asChild>
                  <Link href={route('{$this->entityPluralLower}.index')} className=\"flex items-center gap-2\">
                    <FolderOpen className=\"h-4 w-4\" />
                    {$this->entityPlural}
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>";
            
            if (!str_contains($sidebar, $sidebarItem)) {
                // Check if Management section exists, if not create it
                if (!str_contains($sidebar, '<SidebarGroupLabel>Management</SidebarGroupLabel>')) {
                    // Add Management section after the Main section
                    $managementSection = "
        <SidebarGroup>
          <SidebarGroupLabel>Management</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
{$sidebarItem}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>";
                    
                    // Find the position after the Main section and before the Separator
                    $pattern = '/(<SidebarGroup>\s*<SidebarGroupLabel>Main<\/SidebarGroupLabel>.*?<\/SidebarGroup>\s*)\n\s*<Separator/s';
                    $replacement = '$1' . $managementSection . "\n        <Separator";
                    $sidebar = preg_replace($pattern, $replacement, $sidebar);
                } else {
                    // Management section exists, add to it
                    $managementPattern = '/<SidebarGroupLabel>Management<\/SidebarGroupLabel>/';
                    if (preg_match($managementPattern, $sidebar, $matches, PREG_OFFSET_CAPTURE)) {
                        // Find the last SidebarMenuItem before the closing SidebarMenu tag
                        $lastItemPattern = '/<SidebarMenuItem>.*?<\/SidebarMenuItem>\s*<\/SidebarMenu>/s';
                        if (preg_match($lastItemPattern, $sidebar, $lastItemMatches, PREG_OFFSET_CAPTURE, $matches[0][1])) {
                            $insertPosition = $lastItemMatches[0][1] + strpos($lastItemMatches[0][0], '</SidebarMenuItem>') + strlen('</SidebarMenuItem>');
                            $sidebar = substr_replace($sidebar, "\n" . $sidebarItem, $insertPosition, 0);
                        }
                    }
                }
                
                File::put($sidebarPath, $sidebar);
                $this->info("Sidebar navigation updated");
            }
        }
    }

    protected function askWithMenu(array $options, $question)
    {
        $this->info($question);
        foreach ($options as $index => $option) {
            $this->line(($index + 1) . ". {$option}");
        }

        while (true) {
            $choice = $this->ask('Select option (1-' . count($options) . ')');
            if (is_numeric($choice) && $choice >= 1 && $choice <= count($options)) {
                return $options[$choice - 1];
            }
            $this->error('Invalid choice. Please try again.');
        }
    }
} 