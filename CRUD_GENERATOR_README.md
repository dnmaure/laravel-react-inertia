# Enhanced CRUD Generator for Laravel React with Inertia

This enhanced CRUD generator creates complete CRUD scaffolding for Laravel React applications with Inertia.js, including support for file uploads, long text fields, and various data types.

## Features

- **Model Generation** - Creates Eloquent models with proper fillable fields
- **Migration Generation** - Creates database migrations with appropriate field types
- **Controller Generation** - Creates controllers with validation and file upload handling
- **React Components** - Generates Index, Create, Edit, and Show components
- **Route Generation** - Automatically adds resource routes
- **Navigation Updates** - Updates the authenticated layout navigation
- **File Upload Support** - Handles file uploads with proper storage
- **Multiple Field Types** - Supports string, integer, decimal, boolean, date, text, longtext, email, file, and url

## Available Field Types

| Type | Description | Database Column | Validation |
|------|-------------|----------------|------------|
| `string` | Short text (max 255 chars) | `VARCHAR(255)` | string, max:255 |
| `integer` | Whole numbers | `INTEGER` | integer, min:0 |
| `decimal` | Decimal numbers | `DECIMAL(10,2)` | numeric, min:0 |
| `boolean` | True/false values | `BOOLEAN` | boolean |
| `date` | Date values | `DATE` | date |
| `text` | Medium text | `TEXT` | string |
| `longtext` | Long text content | `LONGTEXT` | string |
| `email` | Email addresses | `VARCHAR(255)` | email |
| `file` | File uploads | `VARCHAR(255)` | file, mimes:jpeg,png,jpg,gif,pdf,doc,docx,mp4,mov,avi, max:10240 |
| `url` | URL links | `VARCHAR(255)` | url |

## Usage

### Basic Usage

```bash
php artisan create:crud EntityName --fields="field1:type,field2:type|required"
```

### Simple File Upload Example

For a Document entity with file uploads:

```bash
php artisan create:crud Document --fields="title:string|required,description:text,file:file|required,category:string"
```

### Course Entity Example

For a Course entity with long descriptions, PDF files, and videos:

```bash
php artisan create:crud Course --fields="title:string|required,slug:string|required,short_description:text|required,long_description:longtext|required,duration:integer|required,price:decimal|required,status:string|required,thumbnail_image:file,pdf_materials:file,video_url:url,video_file:file,difficulty_level:string|required,category_id:integer|required,instructor_id:integer|required,is_featured:boolean,published_at:date"
```

### Interactive Mode

If you don't provide the `--fields` option, the generator will ask you interactively:

```bash
php artisan create:crud Course
```

## Course Entity Field Breakdown

### Basic Information
- `title:string|required` - Course title
- `slug:string|required` - URL-friendly identifier
- `short_description:text|required` - Brief course description
- `long_description:longtext|required` - Detailed course description
- `duration:integer|required` - Course duration in minutes
- `price:decimal|required` - Course price
- `status:string|required` - Course status (draft, published, archived)

### Media Files
- `thumbnail_image:file` - Course thumbnail/cover image
- `pdf_materials:file` - PDF course materials
- `video_url:url` - Video URL (YouTube, Vimeo, etc.)
- `video_file:file` - Uploaded video file

### Additional Metadata
- `difficulty_level:string|required` - Beginner, Intermediate, Advanced
- `category_id:integer|required` - Foreign key to categories table
- `instructor_id:integer|required` - Foreign key to users table
- `is_featured:boolean` - Featured course flag
- `published_at:date` - Publication date

## Generated Files

The generator creates the following files:

### Backend (Laravel)
- `app/Models/Course.php` - Eloquent model
- `database/migrations/YYYY_MM_DD_HHMMSS_create_courses_table.php` - Database migration
- `app/Http/Controllers/CourseController.php` - Controller with CRUD operations

### Frontend (React)
- `resources/js/Pages/Courses/Index.jsx` - List all courses
- `resources/js/Pages/Courses/Create.jsx` - Create new course form
- `resources/js/Pages/Courses/Edit.jsx` - Edit existing course form
- `resources/js/Pages/Courses/Show.jsx` - Display course details

### Routes
- Adds resource routes to `routes/web.php`
- Updates navigation in `resources/js/Layouts/AuthenticatedLayout.jsx`

## File Upload Handling

The generator automatically handles file uploads:

1. **Storage**: Files are stored in `storage/app/public/{entity_plural}/`
2. **Validation**: Supports common file types (images, PDFs, documents, videos)
3. **Size Limit**: 10MB maximum file size
4. **Display**: Show component includes download links for uploaded files

### File Upload Features

- **Automatic File Naming**: Files are renamed with timestamp prefix to avoid conflicts
- **Storage Organization**: Files are stored in entity-specific folders
- **Download Links**: Show component displays clickable links to download files
- **Form Handling**: React forms properly handle file input changes
- **Validation**: Server-side validation for file types and sizes

## Customization

### Adding New Field Types

To add new field types, update the following methods in `CreateCrud.php`:

1. Add to `$availableFieldTypes` array
2. Add case in `generateMigration()` method
3. Add case in `generateController()` validation rules
4. Add case in `getInputType()` method

### Modifying Validation Rules

Edit the validation rules in the `generateController()` method to customize validation for your specific needs.

### Customizing React Components

The generated React components use Tailwind CSS for styling. You can customize the appearance by modifying the generated component files.

## Post-Generation Steps

After running the generator:

1. **Run Migrations**: `php artisan migrate`
2. **Build Assets**: `docker-compose exec node npm run build`
3. **Create Storage Link**: `php artisan storage:link` (if not already done)
4. **Set Permissions**: Ensure storage directory is writable

## Example Generated Controller Methods

### Store Method with File Upload
```php
public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'long_description' => 'required|string',
        'thumbnail_image' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,mp4,mov,avi|max:10240',
        // ... other validation rules
    ]);

    $data = $request->all();
    
    // Handle file uploads
    if ($request->hasFile('thumbnail_image')) {
        $file = $request->file('thumbnail_image');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('public/courses', $fileName);
        $data['thumbnail_image'] = $fileName;
    }

    Course::create($data);

    return redirect()->route('courses.index')
        ->with('success', 'Course created successfully.');
}
```

## Troubleshooting

### Common Issues

1. **File Upload Not Working**: Ensure storage link is created and permissions are set
2. **Validation Errors**: Check that all required fields are provided
3. **Route Not Found**: Verify routes are properly added to `web.php`
4. **Component Not Rendering**: Check that Inertia is properly configured

### File Permissions

Ensure your storage directory has proper write permissions:

```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

### Storage Link

If files are not accessible, create the storage link:

```bash
php artisan storage:link
```

## Advanced Usage

### Custom Validation Rules

You can extend the validation rules by modifying the generated controller:

```php
// In CourseController.php
$request->validate([
    'title' => 'required|string|max:255|unique:courses,title',
    'slug' => 'required|string|unique:courses,slug',
    'price' => 'required|numeric|min:0|max:9999.99',
    // ... custom rules
]);
```

### File Upload Customization

Customize file upload handling in the controller:

```php
// Custom file naming
$fileName = Str::slug($request->title) . '_' . time() . '.' . $file->getClientOriginalExtension();

// Custom storage path
$file->storeAs('public/courses/thumbnails', $fileName);
```

### Multiple File Uploads

To handle multiple files for the same field, you can modify the controller:

```php
// Handle multiple files
if ($request->hasFile('files')) {
    $fileNames = [];
    foreach ($request->file('files') as $file) {
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('public/courses', $fileName);
        $fileNames[] = $fileName;
    }
    $data['files'] = json_encode($fileNames);
}
```

This enhanced CRUD generator provides a solid foundation for building complex applications with file uploads and various data types, while maintaining clean, maintainable code. 