# Laravel React CRUD Generator

This artisan command generates complete CRUD (Create, Read, Update, Delete) operations for Laravel React applications using Inertia.js.

## Features

- ✅ **Model Generation**: Creates Eloquent models with proper fillable fields
- ✅ **Migration Generation**: Creates database migrations with proper field types
- ✅ **Controller Generation**: Creates resource controllers with validation
- ✅ **React Components**: Generates Index, Create, Edit, and Show components
- ✅ **Route Registration**: Automatically adds resource routes to `routes/web.php`
- ✅ **Navigation Updates**: Adds navigation links to `AuthenticatedLayout.jsx`
- ✅ **Field Type Support**: Supports various field types (string, integer, decimal, boolean, date, text, email)
- ✅ **Validation**: Generates proper Laravel validation rules
- ✅ **Form Handling**: Creates forms with proper Inertia.js integration

## Usage

### Basic Usage

```bash
# Generate CRUD with interactive field definition
php artisan create:crud Product

# Generate CRUD with predefined fields
php artisan create:crud Product --fields="name:string,price:decimal,stock:integer"
```

### Field Types Supported

- `string` - VARCHAR(255) field
- `integer` - INTEGER field
- `decimal` - DECIMAL(10,2) field
- `boolean` - BOOLEAN field with default false
- `date` - DATE field
- `text` - TEXT field (nullable)
- `email` - VARCHAR field with email validation

### Field Options

- Add `|required` to make a field required: `name:string|required`
- Fields are nullable by default unless specified as required

## Examples

### Example 1: Simple Product CRUD

```bash
php artisan create:crud Product --fields="name:string|required,price:decimal|required,stock:integer"
```

This creates:
- Model: `app/Models/Product.php`
- Migration: `database/migrations/xxxx_create_products_table.php`
- Controller: `app/Http/Controllers/ProductController.php`
- React Components: `resources/js/Pages/Products/Index.jsx`, `Create.jsx`, `Edit.jsx`, `Show.jsx`
- Routes: `Route::resource('products', ProductController::class);`
- Navigation: Adds "Products" link to navigation

### Example 2: Category CRUD

```bash
php artisan create:crud Category --fields="name:string|required,description:text"
```

### Example 3: User Profile CRUD

```bash
php artisan create:crud Profile --fields="first_name:string|required,last_name:string|required,email:email|required,birth_date:date,is_active:boolean"
```

## Generated Files Structure

```
app/
├── Models/
│   └── {Entity}.php
├── Http/Controllers/
│   └── {Entity}Controller.php
└── Console/Commands/
    └── CreateCrud.php

database/migrations/
└── xxxx_create_{entities}_table.php

resources/js/Pages/
└── {Entities}/
    ├── Index.jsx
    ├── Create.jsx
    ├── Edit.jsx
    └── Show.jsx

routes/
└── web.php (updated with new routes)

resources/js/Layouts/
└── AuthenticatedLayout.jsx (updated with navigation)
```

## Generated Features

### Model
- Proper namespace and inheritance
- Fillable fields based on defined fields
- Ready for relationships and additional methods

### Migration
- Proper table structure
- Field types mapped to Laravel migration methods
- Timestamps included
- Proper rollback method

### Controller
- Full resource controller with all CRUD methods
- Proper validation rules for each field
- Inertia.js integration
- Success messages and redirects
- Route model binding

### React Components

#### Index Component
- Table display of all records
- Pagination support
- Links to Create, Show, and Edit
- Responsive design with Tailwind CSS

#### Create Component
- Form with all defined fields
- Proper input types (text, number, email, date, textarea)
- Validation error display
- Inertia.js form handling

#### Edit Component
- Pre-populated form with existing data
- Same features as Create component
- Update method integration

#### Show Component
- Display all fields in a clean layout
- Links to Edit and back to Index
- Responsive grid layout

## Post-Generation Steps

After running the command:

1. **Build Assets** (if not done automatically):
   ```bash
   docker-compose exec node npm run build
   ```

2. **Run Migrations** (if not done automatically):
   ```bash
   php artisan migrate
   ```

3. **Test the CRUD**:
   - Access the application
   - Login to your account
   - Navigate to the new entity via the navigation menu
   - Test all CRUD operations

## Customization

### Adding Relationships

After generation, you can add relationships to the model:

```php
// In app/Models/Product.php
public function category()
{
    return $this->belongsTo(Category::class);
}
```

### Adding Custom Validation

Modify the controller to add custom validation rules:

```php
// In app/Http/Controllers/ProductController.php
public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255|unique:products',
        'price' => 'required|numeric|min:0',
        'stock' => 'required|integer|min:0',
    ]);
    // ... rest of the method
}
```

### Styling Customization

The React components use Tailwind CSS classes. You can customize the styling by modifying the className attributes in the generated components.

## Troubleshooting

### Migration Errors
If you get "table already exists" errors:
```bash
php artisan tinker --execute="DB::table('migrations')->insert(['migration' => 'migration_name', 'batch' => 1]);"
```

### Build Errors
If you get build errors:
```bash
docker-compose exec node npm run build
```

### JavaScript Errors
If you get `ReferenceError: $entity is not defined`:
- This is a template generation issue that has been fixed in the latest version
- Rebuild assets: `docker-compose exec node npm run build`
- If the error persists, check the generated React components for incorrect `$` symbols in JSX

### Duplicate Controller Imports
If you see duplicate `use App\Http\Controllers\` statements in `routes/web.php`:
- This issue has been fixed in the latest version
- The generator now properly checks for existing imports before adding new ones
- Clean up any existing duplicates manually if needed

### Missing Navigation Links
If navigation links are not appearing in the navbar:
- The generator now uses a robust line-by-line parsing approach to find the navigation section
- It automatically detects existing NavLink elements and adds new ones in the correct location
- The generator checks for existing navigation links before adding new ones to prevent duplicates
- Rebuild assets after generation: `docker-compose exec node npm run build`
- If automatic navigation update fails, the generator will provide the manual navigation link code

### Route Errors
If routes don't work, check:
- Routes are properly added to `routes/web.php`
- Controller namespace is correct
- Migration has been run

## Contributing

To extend the CRUD generator:

1. Modify `app/Console/Commands/CreateCrud.php`
2. Add new field types in the `getInputType()` method
3. Add new validation rules in the controller generation
4. Update React component templates as needed

## License

This CRUD generator is part of your Laravel React application and follows the same license as your project. 