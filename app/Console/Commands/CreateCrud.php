<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Use example: php artisan create:crud Product --fields="name:string,price:decimal,stock:integer"
 */
class CreateCrud extends Command
{
    protected $entityName;
    protected $fields;
    protected $entityLower;
    protected $entityPlural;
    protected $entityPluralLower;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:crud {name? : The entity name} {--fields=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate complete CRUD scaffolding for Laravel React with Inertia';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->entityName = $this->argument('name') ?? $this->ask('What is the entity name?');
        $this->entityLower = strtolower($this->entityName);
        $this->entityPlural = Str::plural($this->entityName);
        $this->entityPluralLower = strtolower($this->entityPlural);
        
        // Define available field types for Laravel
        $availableFieldTypes = ['string', 'integer', 'decimal', 'boolean', 'date', 'text', 'email'];
        
        // Ask for fields with a select menu
        $fields = $this->option('fields') ?? $this->askWithMenu($availableFieldTypes);

        // validate fields
        if (empty($fields)) {
            $this->error('Fields cannot be empty');
            return Command::FAILURE;
        }

        // Parse fields
        $this->fields = $this->parseFields($fields);

        // Generate Model
        $this->generateModel();

        // Generate Migration
        $this->generateMigration();

        // Generate Controller
        $this->generateController();

        // Generate React Components
        $this->generateReactComponents();

        // Add web routes
        $this->addWebRoutes();

        // Update navigation
        $this->updateNavigation();

        // Run Migration
        $this->call('migrate');

        // Build assets
        $this->info('Building frontend assets...');
        $this->info('Please run: docker-compose exec node npm run build');

        $this->info("CRUD for {$this->entityName} created successfully!");
        $this->info("Access your CRUD at: /{$this->entityPluralLower}");
        
        return Command::SUCCESS;
    }

    /**
     * Parse the fields option into an array of field definitions.
     *
     * @param string|null $fields
     * @return array
     */
    protected function parseFields($fields)
    {
        $parsedFields = [];
        if ($fields) {
            $fieldsArray = explode(',', $fields);
            foreach ($fieldsArray as $field) {
                [$name, $type] = explode(':', $field);
                $isRequired = false;

                // Check if the field has a "required" flag
                if (strpos($type, '|required') !== false) {
                    $type = str_replace('|required', '', $type);
                    $isRequired = true;
                }

                $parsedFields[] = [
                    'type' => $type,
                    'name' => $name,
                    'required' => $isRequired,
                ];
            }
        }
        Log::info('Parsed fields:', $parsedFields);
        return $parsedFields;
    }

    /**
     * Generate the Model
     */
    protected function generateModel()
    {
        $fillableFields = implode(",\n        ", array_map(function ($field) {
            return "'{$field['name']}'";
        }, $this->fields));

        $modelContent = "<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class {$this->entityName} extends Model
{
    protected \$fillable = [
        {$fillableFields},
    ];
}";

        $modelPath = app_path("Models/{$this->entityName}.php");
        File::put($modelPath, $modelContent);
        $this->info("Model created: {$modelPath}");
    }

    /**
     * Generate the Migration
     */
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
                case 'email':
                    return "\$table->string('{$name}');";
                default:
                    return "\$table->string('{$name}');";
            }
        }, $this->fields));

        $migrationContent = "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$this->entityPluralLower}', function (Blueprint \$table) {
            \$table->id();
            {$migrationFields}
            \$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$this->entityPluralLower}');
    }
};";

        $migrationFileName = now()->format('Y_m_d_His') . "_create_{$this->entityPluralLower}_table.php";
        $migrationPath = database_path("migrations/{$migrationFileName}");
        File::put($migrationPath, $migrationContent);
        $this->info("Migration created: {$migrationPath}");
    }

    /**
     * Generate the Controller
     */
    protected function generateController()
    {
        $validationRules = implode(",\n            ", array_map(function ($field) {
            $rules = [];
            
            if ($field['required']) {
                $rules[] = 'required';
            } else {
                $rules[] = 'nullable';
            }
            
            switch ($field['type']) {
                case 'string':
                    $rules[] = 'string';
                    $rules[] = 'max:255';
                    break;
                case 'integer':
                    $rules[] = 'integer';
                    $rules[] = 'min:0';
                    break;
                case 'decimal':
                    $rules[] = 'numeric';
                    $rules[] = 'min:0';
                    break;
                case 'boolean':
                    $rules[] = 'boolean';
                    break;
                case 'date':
                    $rules[] = 'date';
                    break;
                case 'text':
                    $rules[] = 'string';
                    break;
                case 'email':
                    $rules[] = 'email';
                    break;
            }
            
            return "'{$field['name']}' => '" . implode('|', $rules) . "'";
        }, $this->fields));

        $controllerContent = "<?php

namespace App\Http\Controllers;

use App\Models\\{$this->entityName};
use Illuminate\Http\Request;
use Inertia\Inertia;

class {$this->entityName}Controller extends Controller
{
    public function index()
    {
        \${$this->entityPluralLower} = {$this->entityName}::latest()->paginate(10);
        
        return Inertia::render('{$this->entityPlural}/Index', [
            '{$this->entityPluralLower}' => \${$this->entityPluralLower},
        ]);
    }

    public function create()
    {
        return Inertia::render('{$this->entityPlural}/Create');
    }

    public function store(Request \$request)
    {
        \$request->validate([
            {$validationRules},
        ]);

        {$this->entityName}::create(\$request->all());

        return redirect()->route('{$this->entityPluralLower}.index')
            ->with('success', '{$this->entityName} created successfully.');
    }

    public function show({$this->entityName} \${$this->entityLower})
    {
        return Inertia::render('{$this->entityPlural}/Show', [
            '{$this->entityLower}' => \${$this->entityLower},
        ]);
    }

    public function edit({$this->entityName} \${$this->entityLower})
    {
        return Inertia::render('{$this->entityPlural}/Edit', [
            '{$this->entityLower}' => \${$this->entityLower},
        ]);
    }

    public function update(Request \$request, {$this->entityName} \${$this->entityLower})
    {
        \$request->validate([
            {$validationRules},
        ]);

        \${$this->entityLower}->update(\$request->all());

        return redirect()->route('{$this->entityPluralLower}.index')
            ->with('success', '{$this->entityName} updated successfully.');
    }

    public function destroy({$this->entityName} \${$this->entityLower})
    {
        \${$this->entityLower}->delete();

        return redirect()->route('{$this->entityPluralLower}.index')
            ->with('success', '{$this->entityName} deleted successfully.');
    }
}";

        $controllerPath = app_path("Http/Controllers/{$this->entityName}Controller.php");
        File::put($controllerPath, $controllerContent);
        $this->info("Controller created: {$controllerPath}");
    }

    /**
     * Generate React Components
     */
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
            user={auth.user}
            header={<h2 className=\"font-semibold text-xl text-gray-800 leading-tight\">{$this->entityPlural}</h2>}
        >
            <Head title=\"{$this->entityPlural}\" />

            <div className=\"py-12\">
                <div className=\"max-w-7xl mx-auto sm:px-6 lg:px-8\">
                    <div className=\"bg-white overflow-hidden shadow-sm sm:rounded-lg\">
                        <div className=\"p-6 text-gray-900\">
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
                    </div>
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
            $isTextarea = $field['type'] === 'text';
            
            if ($isTextarea) {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <textarea
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        value={data.{$field['name']}}
                                        onChange={(e) => setData('{$field['name']}', e.target.value)}
                                        rows=\"3\"
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
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
            return "{$field['name']}: ''";
        }, $this->fields));

        $createContent = "import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Create({ auth, errors }) {
    const { data, setData, post, processing } = useForm({
        {$formData},
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('{$this->entityPluralLower}.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className=\"font-semibold text-xl text-gray-800 leading-tight\">Create {$this->entityName}</h2>}
        >
            <Head title=\"Create {$this->entityName}\" />

            <div className=\"py-12\">
                <div className=\"max-w-7xl mx-auto sm:px-6 lg:px-8\">
                    <div className=\"bg-white overflow-hidden shadow-sm sm:rounded-lg\">
                        <div className=\"p-6 text-gray-900\">
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
                    </div>
                </div>
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
            $isTextarea = $field['type'] === 'text';
            
            if ($isTextarea) {
                return "<div className=\"mb-4\">
                                    <label className=\"block text-gray-700 text-sm font-bold mb-2\">
                                        {$field['name']}
                                    </label>
                                    <textarea
                                        className=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\"
                                        value={data.{$field['name']}}
                                        onChange={(e) => setData('{$field['name']}', e.target.value)}
                                        rows=\"3\"
                                    />
                                    {errors.{$field['name']} && <div className=\"text-red-500 text-xs\">{errors.{$field['name']}}</div>}
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
            return "{$field['name']}: {$this->entityLower}.{$field['name']} || ''";
        }, $this->fields));

        $editContent = "import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({ auth, {$this->entityLower}, errors }) {
    const { data, setData, put, processing } = useForm({
        {$formData},
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('{$this->entityPluralLower}.update', {$this->entityLower}.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className=\"font-semibold text-xl text-gray-800 leading-tight\">Edit {$this->entityName}</h2>}
        >
            <Head title=\"Edit {$this->entityName}\" />

            <div className=\"py-12\">
                <div className=\"max-w-7xl mx-auto sm:px-6 lg:px-8\">
                    <div className=\"bg-white overflow-hidden shadow-sm sm:rounded-lg\">
                        <div className=\"p-6 text-gray-900\">
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
                    </div>
                </div>
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
            return "<div>
                                        <label className=\"block text-sm font-medium text-gray-700\">{$field['name']}</label>
                                        <p className=\"mt-1 text-sm text-gray-900\">{{$this->entityLower}.{$field['name']}}</p>
                                    </div>";
        }, $this->fields));

        $showContent = "import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ auth, {$this->entityLower} }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className=\"font-semibold text-xl text-gray-800 leading-tight\">{$this->entityName} Details</h2>}
        >
            <Head title=\"{$this->entityName} Details\" />

            <div className=\"py-12\">
                <div className=\"max-w-7xl mx-auto sm:px-6 lg:px-8\">
                    <div className=\"bg-white overflow-hidden shadow-sm sm:rounded-lg\">
                        <div className=\"p-6 text-gray-900\">
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}";

        $showPath = resource_path("js/Pages/{$this->entityPlural}/Show.jsx");
        File::put($showPath, $showContent);
        $this->info("Show component created: {$showPath}");
    }

    /**
     * Get input type for form fields
     */
    protected function getInputType($fieldType)
    {
        switch ($fieldType) {
            case 'email':
                return 'email';
            case 'integer':
            case 'decimal':
                return 'number';
            case 'date':
                return 'date';
            case 'boolean':
                return 'checkbox';
            default:
                return 'text';
        }
    }

    /**
     * Add web routes to routes/web.php
     */
    protected function addWebRoutes()
    {
        $routesFile = base_path('routes/web.php');
        
        // Read the file
        $content = File::get($routesFile);
        
        // Add the use statement at the top
        $useStatement = "use App\\Http\\Controllers\\{$this->entityName}Controller;";
        
        // Check if the use statement already exists
        if (!str_contains($content, $useStatement)) {
            // Find the last use statement and add after it
            $lines = explode("\n", $content);
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
            
            $content = implode("\n", $newLines);
        }
        
        // Add the route inside the auth middleware group
        $routeContent = "\n        // {$this->entityName} routes\n        Route::resource('{$this->entityPluralLower}', {$this->entityName}Controller::class);\n";
        
        // Find the auth middleware group and add the route
        $pattern = '/(Route::middleware\(\'auth\'\)->group\(function \(\) \{)(.*?)(\}\);)/s';
        $replacement = '$1$2' . $routeContent . '$3';
        
        $newContent = preg_replace($pattern, $replacement, $content);
        
        File::put($routesFile, $newContent);
        
        $this->info("Routes added to routes/web.php");
    }

    /**
     * Update navigation in AuthenticatedLayout
     */
    protected function updateNavigation()
    {
        $layoutFile = resource_path('js/Layouts/AuthenticatedLayout.jsx');
        
        if (!File::exists($layoutFile)) {
            $this->warn("AuthenticatedLayout.jsx not found, skipping navigation update");
            return;
        }

        $content = File::get($layoutFile);
        
        // Check if navigation link already exists
        $navLinkText = "route('{$this->entityPluralLower}.index')";
        if (str_contains($content, $navLinkText)) {
            $this->info("Navigation link already exists for {$this->entityName}");
            return;
        }
        
        // Add navigation link
        $navLink = "                                <NavLink\n                                    href={route('{$this->entityPluralLower}.index')}\n                                    active={route().current('{$this->entityPluralLower}.*')}\n                                >\n                                    {$this->entityName}\n                                </NavLink>\n";
        
        // Find the navigation section and add the link before the closing div
        // Try to find the navigation section and insert the link
        $lines = explode("\n", $content);
        $newLines = [];
        $navSectionFound = false;
        $navLinkAdded = false;
        
        foreach ($lines as $line) {
            $newLines[] = $line;
            
            // Look for the navigation section opening
            if (str_contains($line, 'className="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex"') || 
                str_contains($line, 'className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"')) {
                $navSectionFound = true;
            }
            
            // If we're in the navigation section and find the closing div, add our link before it
            if ($navSectionFound && str_contains($line, '</div>') && !$navLinkAdded) {
                // Check if there are any NavLink elements before this closing div
                $hasNavLinks = false;
                foreach (array_slice($newLines, -10) as $prevLine) {
                    if (str_contains($prevLine, 'NavLink')) {
                        $hasNavLinks = true;
                        break;
                    }
                }
                
                if ($hasNavLinks) {
                    // Insert our navigation link before the closing div
                    array_pop($newLines); // Remove the closing div
                    $newLines[] = $navLink;
                    $newLines[] = $line; // Add back the closing div
                    $navLinkAdded = true;
                }
            }
        }
        
        if ($navLinkAdded) {
            $newContent = implode("\n", $newLines);
            File::put($layoutFile, $newContent);
            $this->info("Navigation updated in AuthenticatedLayout.jsx");
        } else {
            $this->warn("Could not find navigation section in AuthenticatedLayout.jsx");
            $this->info("Please manually add the navigation link:");
            $this->info($navLink);
        }
    }

    /**
     * Ask for fields using a select menu.
     *
     * @param array $availableFieldTypes
     * @return string
     */
    protected function askWithMenu(array $availableFieldTypes)
    {
        $this->info('Define the fields for your entity (e.g., name:string,price:decimal)');
        $fields = [];

        while (true) {
            $fieldName = $this->ask('Enter the field name (or press Enter to finish):');
            if (empty($fieldName)) {
                break;
            }

            $fieldType = $this->choice('Select the field type:', $availableFieldTypes, 0);

            $isRequired = $this->confirm('Is this field required?', false);

            $fields[] = "{$fieldName}:{$fieldType}" . ($isRequired ? '|required' : '');
        }

        return implode(',', $fields);
    }
} 