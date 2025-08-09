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
        'string', 'integer', 'decimal', 'boolean', 'checkbox', 'date', 'text', 
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
        $this->generateFactory();
        $this->generateTests();
        $this->generateReactComponents();
        $this->addWebRoutes();
        $this->updateNavigation();

        // Run migration if table doesn't exist
        $this->runMigrationIfTableDoesNotExist();

        $this->info("CRUD for {$this->entityName} created successfully!");
        $this->info("Generated components:");
        $this->info("- Model, Migration, Controller");
        $this->info("- Factory for testing");
        $this->info("- Unit and Feature tests");
        $this->info("- React components (Index, Create, Edit, Show)");
        $this->info("- Routes and navigation");
        $this->info("");
        $this->info("Next steps:");
        $this->info("- Run tests: php artisan test --filter={$this->entityName}");
        $this->info("- Don't forget to run: php artisan storage:link");
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
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        $fillableFields = implode(",\n        ", array_map(function ($field) {
            return "'{$field['name']}'";
        }, $filteredFields));

        // Generate casts for date and boolean fields
        $dateFields = array_filter($filteredFields, function ($field) {
            return $field['type'] === 'date';
        });
        
        $booleanFields = array_filter($filteredFields, function ($field) {
            return $field['type'] === 'boolean' || $field['type'] === 'checkbox';
        });
        
        $castFields = [];
        foreach ($dateFields as $field) {
            $castFields[] = "'{$field['name']}' => 'date'";
        }
        foreach ($booleanFields as $field) {
            $castFields[] = "'{$field['name']}' => 'boolean'";
        }
        
        $dateCasts = '';
        if (!empty($castFields)) {
            $casts = implode(",\n        ", $castFields);
            $dateCasts = "

    protected \$casts = [
        {$casts},
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
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        $migrationFields = implode("\n            ", array_map(function ($field) {
            $type = $field['type'];
            $name = $field['name'];
            $isRequired = $this->shouldFieldBeRequired($field);
            $nullable = $isRequired ? '' : '->nullable()';
            
            switch ($type) {
                case 'string':
                    return "\$table->string('{$name}'){$nullable};";
                case 'integer':
                    return "\$table->integer('{$name}'){$nullable};";
                case 'decimal':
                    return "\$table->decimal('{$name}', 10, 2){$nullable};";
                case 'boolean':
                    return "\$table->boolean('{$name}')->default(false);";
                case 'checkbox':
                    return "\$table->boolean('{$name}')->default(false);";
                case 'date':
                    return "\$table->date('{$name}'){$nullable};";
                case 'text':
                    return "\$table->text('{$name}')->nullable();";
                case 'longtext':
                    return "\$table->longText('{$name}')->nullable();";
                case 'email':
                    return "\$table->string('{$name}'){$nullable};";
                case 'file':
                    return "\$table->string('{$name}')->nullable();";
                case 'url':
                    return "\$table->string('{$name}'){$nullable};";
                default:
                    return "\$table->string('{$name}'){$nullable};";
            }
        }, $filteredFields));

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
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        $validationRules = implode(",\n            ", array_map(function ($field) {
            $rules = [];
            
            // Determine if field should be required based on type and database constraints
            $isRequired = $this->shouldFieldBeRequired($field);
            $baseRule = $isRequired ? 'required' : 'nullable';
            
            switch ($field['type']) {
                case 'string':
                    $rules[] = $baseRule . '|string|max:255';
                    break;
                case 'text':
                    $rules[] = $baseRule . '|string|max:65535';
                    break;
                case 'longtext':
                    $rules[] = $baseRule . '|string';
                    break;
                case 'integer':
                    $rules[] = $baseRule . '|integer';
                    break;
                case 'decimal':
                    $rules[] = $baseRule . '|numeric|between:0,999999.99';
                    break;
                case 'boolean':
                    $rules[] = 'boolean';
                    break;
                case 'checkbox':
                    $rules[] = 'boolean';
                    break;
                case 'date':
                    $rules[] = $baseRule . '|date';
                    break;
                case 'email':
                    $rules[] = $baseRule . '|email|max:255';
                    break;
                case 'file':
                    $rules[] = 'nullable|file|max:10240'; // 10MB max
                    break;
                case 'url':
                    $rules[] = $baseRule . '|url|max:255';
                    break;
                default:
                    $rules[] = $baseRule . '|string|max:255';
            }
            
            return "'{$field['name']}' => '" . implode('|', $rules) . "'";
        }, $filteredFields));

        // Generate file upload handling code
        $fileUploadCode = '';
        $dateFormatCode = '';
        foreach ($filteredFields as $field) {
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
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        // Generate columns for data table
        $columns = implode("\n", array_map(function ($field) {
            if ($field['type'] === 'boolean' || $field['type'] === 'checkbox') {
                $template = File::get(resource_path('templates/column_boolean.stub'));
            } elseif ($field['type'] === 'date') {
                $template = File::get(resource_path('templates/column_date.stub'));
            } elseif ($field['type'] === 'url') {
                $template = File::get(resource_path('templates/column_url.stub'));
            } else {
                $template = File::get(resource_path('templates/column_text.stub'));
            }
            return str_replace('{{fieldName}}', $field['name'], $template);
        }, $filteredFields));

        // Generate Index component
        $template = File::get(resource_path('templates/react_index.stub'));
        $indexContent = str_replace([
            '{{entityName}}',
            '{{entityPlural}}',
            '{{entityPluralLower}}',
            '{{entityLower}}',
            '{{entity}}'
        ], [
            $this->entityName,
            $this->entityPlural,
            $this->entityPluralLower,
            $this->entityLower,
            $this->entityName
        ], $template);

        $indexPath = resource_path("js/Pages/{$this->entityPlural}/Index.jsx");
        File::put($indexPath, $indexContent);
        $this->info("Index component created: {$indexPath}");

        // Generate columns definition file
        $columnsTemplate = File::get(resource_path('templates/columns.stub'));
        $columnsContent = str_replace([
            '{{columns}}',
            '{{entityPluralLower}}',
            '{{entityLower}}'
        ], [
            $columns,
            $this->entityPluralLower,
            $this->entityLower
        ], $columnsTemplate);

        $columnsPath = resource_path("js/Pages/{$this->entityPlural}/columns.jsx");
        File::put($columnsPath, $columnsContent);
        $this->info("Columns definition created: {$columnsPath}");
    }

    protected function generateCreateComponent()
    {
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        $formFields = implode("\n", array_map(function ($field) {
            $inputType = $this->getInputType($field['type']);
            $isTextarea = $field['type'] === 'text' || $field['type'] === 'longtext';
            
            if ($isTextarea) {
                $rows = $field['type'] === 'longtext' ? '6' : '3';
                $template = File::get(resource_path('templates/form_field_textarea.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{rows}}'
                ], [
                    $field['name'],
                    $rows
                ], $template);
            } elseif ($field['type'] === 'file') {
                $template = File::get(resource_path('templates/form_field_file.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } elseif ($field['type'] === 'date') {
                $template = File::get(resource_path('templates/form_field_date.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } elseif ($field['type'] === 'boolean') {
                $template = File::get(resource_path('templates/form_field_switch.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } elseif ($field['type'] === 'checkbox') {
                $template = File::get(resource_path('templates/form_field_checkbox.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } else {
                $template = File::get(resource_path('templates/form_field_text.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{inputType}}'
                ], [
                    $field['name'],
                    $inputType
                ], $template);
            }
        }, $filteredFields));

        $formData = implode(",\n        ", array_map(function ($field) {
            if ($field['type'] === 'file') {
                return "{$field['name']}: null";
            } elseif ($field['type'] === 'date') {
                return "{$field['name']}: null";
            } elseif ($field['type'] === 'boolean') {
                return "{$field['name']}: false";
            } elseif ($field['type'] === 'checkbox') {
                return "{$field['name']}: false";
            }
            return "{$field['name']}: ''";
        }, $filteredFields));

        // Check if we have date fields for conditional imports
        $hasDateFields = !empty(array_filter($filteredFields, function ($field) {
            return $field['type'] === 'date';
        }));

        // Check if we have boolean fields for conditional imports
        $hasBooleanFields = !empty(array_filter($filteredFields, function ($field) {
            return $field['type'] === 'boolean';
        }));

        // Check if we have checkbox fields for conditional imports
        $hasCheckboxFields = !empty(array_filter($filteredFields, function ($field) {
            return $field['type'] === 'checkbox';
        }));

        $datePickerImport = $hasDateFields ? "import DatePicker from '@/Components/ui/DatePicker';" : "";
        $switchImport = $hasBooleanFields ? "import { Switch } from '@/Components/ui/ui/switch';" : "";
        $checkboxImport = $hasCheckboxFields ? "import { Checkbox } from '@/Components/ui/ui/checkbox';" : "";
        $labelImport = $hasCheckboxFields ? "import { Label } from '@/Components/ui/ui/label';" : "";

        $template = File::get(resource_path('templates/react_create.stub'));
        $createContent = str_replace([
            '{{entityName}}',
            '{{entityPlural}}',
            '{{entityPluralLower}}',
            '{{formData}}',
            '{{formFields}}',
            '{{datePickerImport}}',
            '{{switchImport}}',
            '{{checkboxImport}}',
            '{{labelImport}}'
        ], [
            $this->entityName,
            $this->entityPlural,
            $this->entityPluralLower,
            $formData,
            $formFields,
            $datePickerImport,
            $switchImport,
            $checkboxImport,
            $labelImport
        ], $template);

        $createPath = resource_path("js/Pages/{$this->entityPlural}/Create.jsx");
        File::put($createPath, $createContent);
        $this->info("Create component created: {$createPath}");
    }

    protected function generateEditComponent()
    {
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        $formFields = implode("\n", array_map(function ($field) {
            $inputType = $this->getInputType($field['type']);
            $isTextarea = $field['type'] === 'text' || $field['type'] === 'longtext';
            
            if ($isTextarea) {
                $rows = $field['type'] === 'longtext' ? '6' : '3';
                $template = File::get(resource_path('templates/form_field_textarea.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{rows}}'
                ], [
                    $field['name'],
                    $rows
                ], $template);
            } elseif ($field['type'] === 'file') {
                $template = File::get(resource_path('templates/form_field_file.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } elseif ($field['type'] === 'date') {
                $template = File::get(resource_path('templates/form_field_date.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } elseif ($field['type'] === 'boolean') {
                $template = File::get(resource_path('templates/form_field_switch.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } elseif ($field['type'] === 'checkbox') {
                $template = File::get(resource_path('templates/form_field_checkbox.stub'));
                return str_replace('{{fieldName}}', $field['name'], $template);
            } else {
                $template = File::get(resource_path('templates/form_field_text.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{inputType}}'
                ], [
                    $field['name'],
                    $inputType
                ], $template);
            }
        }, $filteredFields));

        $formData = implode(",\n        ", array_map(function ($field) {
            if ($field['type'] === 'file') {
                return "{$field['name']}: null";
            } elseif ($field['type'] === 'date') {
                return "{$field['name']}: {$this->entityLower}.{$field['name']} || null";
            } elseif ($field['type'] === 'boolean') {
                return "{$field['name']}: {$this->entityLower}.{$field['name']} || false";
            } elseif ($field['type'] === 'checkbox') {
                return "{$field['name']}: {$this->entityLower}.{$field['name']} || false";
            }
            return "{$field['name']}: {$this->entityLower}.{$field['name']} || ''";
        }, $filteredFields));

        // Check if we have date fields for conditional imports
        $hasDateFields = !empty(array_filter($filteredFields, function ($field) {
            return $field['type'] === 'date';
        }));

        // Check if we have boolean fields for conditional imports
        $hasBooleanFields = !empty(array_filter($filteredFields, function ($field) {
            return $field['type'] === 'boolean';
        }));

        // Check if we have checkbox fields for conditional imports
        $hasCheckboxFields = !empty(array_filter($filteredFields, function ($field) {
            return $field['type'] === 'checkbox';
        }));

        $datePickerImport = $hasDateFields ? "import DatePicker from '@/Components/ui/DatePicker';" : "";
        $switchImport = $hasBooleanFields ? "import { Switch } from '@/Components/ui/ui/switch';" : "";
        $checkboxImport = $hasCheckboxFields ? "import { Checkbox } from '@/Components/ui/ui/checkbox';" : "";
        $labelImport = $hasCheckboxFields ? "import { Label } from '@/Components/ui/ui/label';" : "";

        $template = File::get(resource_path('templates/react_edit.stub'));
        $editContent = str_replace([
            '{{entityName}}',
            '{{entityPlural}}',
            '{{entityPluralLower}}',
            '{{entityLower}}',
            '{{formData}}',
            '{{formFields}}',
            '{{datePickerImport}}',
            '{{switchImport}}',
            '{{checkboxImport}}',
            '{{labelImport}}'
        ], [
            $this->entityName,
            $this->entityPlural,
            $this->entityPluralLower,
            $this->entityLower,
            $formData,
            $formFields,
            $datePickerImport,
            $switchImport,
            $checkboxImport,
            $labelImport
        ], $template);

        $editPath = resource_path("js/Pages/{$this->entityPlural}/Edit.jsx");
        File::put($editPath, $editContent);
        $this->info("Edit component created: {$editPath}");
    }

    protected function generateShowComponent()
    {
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        $showFields = implode("\n", array_map(function ($field) {
            if ($field['type'] === 'file') {
                $template = File::get(resource_path('templates/show_field_file.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{entityLower}}',
                    '{{entityPluralLower}}'
                ], [
                    $field['name'],
                    $this->entityLower,
                    $this->entityPluralLower
                ], $template);
            } elseif ($field['type'] === 'date') {
                $template = File::get(resource_path('templates/show_field_date.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{entityLower}}'
                ], [
                    $field['name'],
                    $this->entityLower
                ], $template);
            } elseif ($field['type'] === 'url') {
                $template = File::get(resource_path('templates/show_field_url.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{entityLower}}'
                ], [
                    $field['name'],
                    $this->entityLower
                ], $template);
            } else {
                $template = File::get(resource_path('templates/show_field_text.stub'));
                return str_replace([
                    '{{fieldName}}',
                    '{{entityLower}}'
                ], [
                    $field['name'],
                    $this->entityLower
                ], $template);
            }
        }, $filteredFields));

        $template = File::get(resource_path('templates/react_show.stub'));
        $showContent = str_replace([
            '{{entityName}}',
            '{{entityPlural}}',
            '{{entityPluralLower}}',
            '{{entityLower}}',
            '{{showFields}}'
        ], [
            $this->entityName,
            $this->entityPlural,
            $this->entityPluralLower,
            $this->entityLower,
            $showFields
        ], $template);

        $showPath = resource_path("js/Pages/{$this->entityPlural}/Show.jsx");
        File::put($showPath, $showContent);
        $this->info("Show component created: {$showPath}");
    }

    protected function generateFactory()
    {
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        $factoryFields = implode(",\n            ", array_map(function ($field) {
            $faker = $this->getFakerMethod($field['type']);
            return "'{$field['name']}' => {$faker}";
        }, $filteredFields));

        $template = File::get(resource_path('templates/factory.stub'));
        $content = str_replace([
            '{{entity}}',
            '{{factoryFields}}'
        ], [
            $this->entityName,
            $factoryFields
        ], $template);

        $factoryPath = database_path("factories/{$this->entityName}Factory.php");
        File::put($factoryPath, $content);
        $this->info("Factory created: {$factoryPath}");
    }

    protected function generateTests()
    {
        $this->generateUnitTest();
        $this->generateFeatureTest();
    }

    protected function generateUnitTest()
    {
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        // Generate test data
        $testData = implode(",\n            ", array_map(function ($field) {
            $value = $this->getTestValue($field['type']);
            return "'{$field['name']}' => {$value}";
        }, $filteredFields));

        // Generate update data
        $updateData = implode(",\n            ", array_map(function ($field) {
            $value = $this->getTestValue($field['type'], true);
            return "'{$field['name']}' => {$value}";
        }, $filteredFields));

        // Generate fillable fields array
        $fillableFields = implode(",\n            ", array_map(function ($field) {
            return "'{$field['name']}'";
        }, $filteredFields));

        $template = File::get(resource_path('templates/unit_test.stub'));
        $content = str_replace([
            '{{entity}}',
            '{{entityLower}}',
            '{{entityPluralLower}}',
            '{{testData}}',
            '{{updateData}}',
            '{{fillableFields}}'
        ], [
            $this->entityName,
            $this->entityLower,
            $this->entityPluralLower,
            $testData,
            $updateData,
            $fillableFields
        ], $template);

        $unitTestPath = base_path("tests/Unit/{$this->entityName}Test.php");
        File::put($unitTestPath, $content);
        $this->info("Unit test created: {$unitTestPath}");
    }

    protected function generateFeatureTest()
    {
        // Filter out reserved field names
        $reservedFields = ['id', 'created_at', 'updated_at'];
        $filteredFields = array_filter($this->fields, function ($field) use ($reservedFields) {
            return !in_array($field['name'], $reservedFields);
        });

        // Generate test data
        $testData = implode(",\n            ", array_map(function ($field) {
            $value = $this->getTestValue($field['type']);
            return "'{$field['name']}' => {$value}";
        }, $filteredFields));

        // Generate update data
        $updateData = implode(",\n            ", array_map(function ($field) {
            $value = $this->getTestValue($field['type'], true);
            return "'{$field['name']}' => {$value}";
        }, $filteredFields));

        // Generate validation tests
        $validationTests = $this->generateValidationTests($filteredFields);

        $template = File::get(resource_path('templates/feature_test.stub'));
        $content = str_replace([
            '{{entity}}',
            '{{entityLower}}',
            '{{entityPluralLower}}',
            '{{testData}}',
            '{{updateData}}',
            '{{validationTests}}'
        ], [
            $this->entityName,
            $this->entityLower,
            $this->entityPluralLower,
            $testData,
            $updateData,
            $validationTests
        ], $template);

        $featureTestPath = base_path("tests/Feature/{$this->entityName}ControllerTest.php");
        File::put($featureTestPath, $content);
        $this->info("Feature test created: {$featureTestPath}");
    }

    protected function getFakerMethod($fieldType)
    {
        switch ($fieldType) {
            case 'string':
                return '$this->faker->words(3, true)';
            case 'email':
                return '$this->faker->safeEmail()';
            case 'url':
                return '$this->faker->url()';
            case 'text':
                return '$this->faker->paragraph()';
            case 'longtext':
                return '$this->faker->paragraphs(3, true)';
            case 'integer':
                return '$this->faker->numberBetween(1, 1000)';
            case 'decimal':
                return '$this->faker->randomFloat(2, 0, 9999.99)';
            case 'boolean':
            case 'checkbox':
                return '$this->faker->boolean()';
            case 'date':
                return '$this->faker->date()';
            case 'file':
                return "'test-file.jpg'";
            default:
                return '$this->faker->word()';
        }
    }

    protected function getTestValue($fieldType, $alternate = false)
    {
        switch ($fieldType) {
            case 'string':
                return $alternate ? "'Updated Test String'" : "'Test String'";
            case 'text':
                return $alternate ? "'Updated test paragraph.'" : "'Test paragraph.'";
            case 'longtext':
                return $alternate ? "'Updated long test content.'" : "'Long test content.'";
            case 'integer':
                return $alternate ? '200' : '100';
            case 'decimal':
                return $alternate ? '99.99' : '50.00';
            case 'boolean':
            case 'checkbox':
                return $alternate ? 'false' : 'true';
            case 'date':
                return $alternate ? "'2024-12-31'" : "'2024-01-01'";
            case 'email':
                return $alternate ? "'updated@example.com'" : "'test@example.com'";
            case 'url':
                return $alternate ? "'https://updated.example.com'" : "'https://example.com'";
            case 'file':
                return $alternate ? "'updated-file.jpg'" : "'test-file.jpg'";
            default:
                return $alternate ? "'updated'" : "'test'";
        }
    }

    protected function generateFieldValidationTest($field)
    {
        $rules = $this->getValidationRules($field['type']);
        if (empty($rules)) {
            return '';
        }

        $testName = "test_{$field['name']}_validation";
        return "    public function {$testName}(): void
    {
        \$invalidData = ['{$field['name']}' => {$this->getInvalidTestValue($field['type'])}];
        
        \$this->expectException(\\Illuminate\\Validation\\ValidationException::class);
        
        {$this->entityName}::create(\$invalidData);
    }";
    }

    protected function getValidationRules($fieldType)
    {
        switch ($fieldType) {
            case 'email':
                return ['email'];
            case 'integer':
                return ['integer'];
            case 'decimal':
                return ['numeric'];
            case 'boolean':
            case 'checkbox':
                return ['boolean'];
            case 'date':
                return ['date'];
            case 'url':
                return ['url'];
            default:
                return [];
        }
    }

    protected function getInvalidTestValue($fieldType)
    {
        switch ($fieldType) {
            case 'email':
                return "'invalid-email'";
            case 'integer':
                return "'not-an-integer'";
            case 'decimal':
                return "'not-a-number'";
            case 'boolean':
            case 'checkbox':
                return "'not-a-boolean'";
            case 'date':
                return "'invalid-date'";
            case 'url':
                return "'not-a-url'";
            default:
                return "123"; // This should be invalid for string fields expecting string
        }
    }

    protected function generateValidationTests($fields)
    {
        $tests = [];
        
        foreach ($fields as $field) {
            $rules = $this->getValidationRules($field['type']);
            if (!empty($rules)) {
                $testName = "test_{$field['name']}_validation_fails_with_invalid_data";
                $invalidValue = $this->getInvalidTestValue($field['type']);
                
                $tests[] = "    public function {$testName}(): void
    {
        \$invalidData = ['{$field['name']}' => {$invalidValue}];

        \$response = \$this
            ->actingAs(\$this->user)
            ->post(route('{$this->entityPluralLower}.store'), \$invalidData);

        \$response->assertSessionHasErrors('{$field['name']}');
    }";
            }
        }
        
        return implode("\n\n", $tests);
    }

    protected function shouldFieldBeRequired($field)
    {
        // Fields that should typically be required
        $typicallyRequired = ['string', 'integer', 'decimal', 'email'];
        
        // Fields that are usually optional
        $typicallyOptional = ['text', 'longtext', 'file', 'url', 'date'];
        
        // Boolean/checkbox fields are never required (they have default values)
        if (in_array($field['type'], ['boolean', 'checkbox'])) {
            return false;
        }
        
        // Check if field name suggests it should be required
        $requiredKeywords = ['name', 'title', 'email', 'price', 'amount', 'quantity'];
        foreach ($requiredKeywords as $keyword) {
            if (str_contains(strtolower($field['name']), $keyword)) {
                return true;
            }
        }
        
        // Default based on type
        return in_array($field['type'], $typicallyRequired);
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
            case 'checkbox':
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
                // Check if Management section exists
                if (str_contains($sidebar, '<SidebarGroupLabel>Management</SidebarGroupLabel>')) {
                    // Management section exists, add to it
                    // Find the Management section and insert before the closing SidebarMenu tag
                    $pattern = '/(<SidebarGroupLabel>Management<\/SidebarGroupLabel>\s*<SidebarGroupContent>\s*<SidebarMenu>)(.*?)(<\/SidebarMenu>\s*<\/SidebarGroupContent>\s*<\/SidebarGroup>)/s';
                    $replacement = '$1$2' . $sidebarItem . '$3';
                    $sidebar = preg_replace($pattern, $replacement, $sidebar);
                } else {
                    // Management section doesn't exist, create it
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