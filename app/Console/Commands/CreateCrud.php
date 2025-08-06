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
                case 'checkbox':
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
                case 'checkbox':
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
        $tableHeaders = implode("\n", array_map(function ($field) {
            $template = File::get(resource_path('templates/table_header.stub'));
            return str_replace('{{fieldName}}', $field['name'], $template);
        }, $this->fields));

        $tableCells = implode("\n", array_map(function ($field) {
            $template = File::get(resource_path('templates/table_cell.stub'));
            return str_replace([
                '{{fieldName}}',
                '{{entityLower}}'
            ], [
                $field['name'],
                $this->entityLower
            ], $template);
        }, $this->fields));

        $template = File::get(resource_path('templates/react_index.stub'));
        $indexContent = str_replace([
            '{{entityName}}',
            '{{entityPlural}}',
            '{{entityPluralLower}}',
            '{{entityLower}}',
            '{{tableHeaders}}',
            '{{tableCells}}'
        ], [
            $this->entityName,
            $this->entityPlural,
            $this->entityPluralLower,
            $this->entityLower,
            $tableHeaders,
            $tableCells
        ], $template);

        $indexPath = resource_path("js/Pages/{$this->entityPlural}/Index.jsx");
        File::put($indexPath, $indexContent);
        $this->info("Index component created: {$indexPath}");
    }

    protected function generateCreateComponent()
    {
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
        }, $this->fields));

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
        }, $this->fields));

        // Check if we have date fields for conditional imports
        $hasDateFields = !empty(array_filter($this->fields, function ($field) {
            return $field['type'] === 'date';
        }));

        // Check if we have boolean fields for conditional imports
        $hasBooleanFields = !empty(array_filter($this->fields, function ($field) {
            return $field['type'] === 'boolean';
        }));

        // Check if we have checkbox fields for conditional imports
        $hasCheckboxFields = !empty(array_filter($this->fields, function ($field) {
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
        }, $this->fields));

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
        }, $this->fields));

        // Check if we have date fields for conditional imports
        $hasDateFields = !empty(array_filter($this->fields, function ($field) {
            return $field['type'] === 'date';
        }));

        // Check if we have boolean fields for conditional imports
        $hasBooleanFields = !empty(array_filter($this->fields, function ($field) {
            return $field['type'] === 'boolean';
        }));

        // Check if we have checkbox fields for conditional imports
        $hasCheckboxFields = !empty(array_filter($this->fields, function ($field) {
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
        }, $this->fields));

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