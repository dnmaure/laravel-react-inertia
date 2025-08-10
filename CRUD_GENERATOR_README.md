# Laravel React CRUD Generator

A powerful Laravel console command that generates complete CRUD (Create, Read, Update, Delete) functionality with React components using Inertia.js.

## Features

- ✅ **Complete CRUD Generation**: Model, Migration, Controller, and React Components
- ✅ **Advanced Field Types**: string, integer, decimal, boolean, date, text, longtext, email, file, url
- ✅ **File Upload Support**: Automatic file handling with storage to public disk
- ✅ **Date Picker Components**: shadcn/ui DatePicker integration for date fields
- ✅ **Template-Based Generation**: Clean, maintainable templates for all generated code
- ✅ **Automatic Navigation**: Adds menu items to AuthenticatedLayout
- ✅ **Smart Migration**: Only runs if table doesn't exist
- ✅ **Form Validation**: Automatic validation rules based on field types
- ✅ **Responsive Design**: Tailwind CSS styling with modern UI

## Installation

1. Ensure you have the required dependencies:
   ```bash
   # shadcn/ui components
   npm install date-fns lucide-react clsx tailwind-merge
   
   # Create storage link for file uploads
   php artisan storage:link
   ```

2. The command is already available in your Laravel application.

## Usage

### Interactive Mode
```bash
php artisan create:crud Product
```

The command will prompt you to:
1. Enter field names
2. Select field types from a menu
3. Continue until you're done (press Enter to finish)

### Command Line Mode
```bash
php artisan create:crud Product --fields="name:string,price:decimal,description:text,image:file,created_at:date"
```

## Supported Field Types

| Type | Description | Database Column | Form Input |
|------|-------------|----------------|------------|
| `string` | Short text | `VARCHAR(255)` | Text input |
| `integer` | Whole numbers | `INT` | Number input |
| `decimal` | Decimal numbers | `DECIMAL(10,2)` | Number input |
| `boolean` | True/False | `BOOLEAN` | Checkbox |
| `date` | Date only | `DATE` | DatePicker component |
| `text` | Long text | `TEXT` | Textarea (3 rows) |
| `longtext` | Very long text | `LONGTEXT` | Textarea (6 rows) |
| `email` | Email address | `VARCHAR(255)` | Email input |
| `file` | File upload | `VARCHAR(255)` | File input |
| `url` | URL/Website | `VARCHAR(255)` | URL input |

## Generated Files

The command creates the following files:

### Backend (Laravel)
- `app/Models/{Entity}.php` - Eloquent model with fillable fields and date casting
- `app/Http/Controllers/{Entity}Controller.php` - Full CRUD controller with validation
- `database/migrations/{timestamp}_create_{entities}_table.php` - Database migration
- `routes/web.php` - Resource routes (automatically added)

### Frontend (React)
- `resources/js/Pages/{Entities}/Index.jsx` - List view with table
- `resources/js/Pages/{Entities}/Create.jsx` - Create form
- `resources/js/Pages/{Entities}/Edit.jsx` - Edit form
- `resources/js/Pages/{Entities}/Show.jsx` - Detail view
- `resources/js/Layouts/AuthenticatedLayout.jsx` - Navigation menu (updated)

## Template System

The generator uses a clean template system located in `resources/templates/`:

- `model.stub` - Eloquent model template
- `controller.stub` - Laravel controller template
- `migration.stub` - Database migration template
- `react_index.stub` - React index component template
- `react_create.stub` - React create component template
- `react_edit.stub` - React edit component template
- `react_show.stub` - React show component template

### Template Placeholders

Templates use simple placeholder replacement:
- `{{entity}}` - Entity name (singular, PascalCase)
- `{{entityLower}}` - Entity name (singular, camelCase)
- `{{entityPlural}}` - Entity name (plural, PascalCase)
- `{{entityPluralLower}}` - Entity name (plural, kebab-case)
- `{{fields}}` - Field definitions
- `{{fieldsValidation}}` - Validation rules
- `{{fieldsFillable}}` - Fillable fields
- `{{migrationFields}}` - Migration field definitions
- `{{fileUploadCode}}` - File upload handling code
- `{{dateFormatCode}}` - Date formatting code
- `{{dateCasts}}` - Date casting definitions

## Date Picker Component

The generator automatically integrates the shadcn/ui DatePicker component for `date` fields:

### Features
- Modern calendar interface
- Date formatting with `date-fns`
- Error handling integration
- Responsive design

### Dependencies
- `date-fns` - Date utility library
- `lucide-react` - Icon library
- `clsx` and `tailwind-merge` - Class name utilities

### Usage in Templates
```jsx
import DatePicker from '@/Components/ui/DatePicker';

<DatePicker
    value={data.fieldName}
    onChange={(date) => setData('fieldName', date)}
    placeholder="Select field name"
    error={errors.fieldName}
/>
```

## File Upload Features

### Storage
- Files are stored in `storage/app/public/{entityPlural}/`
- Automatic file naming with timestamps
- Public disk configuration

### Display
- Images are displayed inline with thumbnails
- Non-image files show download links
- Responsive image sizing

### Validation
- Automatic file validation rules
- Support for various file types
- Configurable file size limits

## Examples

### Create a Product CRUD
```bash
php artisan create:crud Product --fields="name:string,price:decimal,description:text,image:file,is_active:boolean"
```

### Create an Event CRUD
```bash
php artisan create:crud Event --fields="title:string,start_date:date,end_date:date,description:longtext,website:url"
```

### Create a User Profile CRUD
```bash
php artisan create:crud Profile --fields="bio:text,avatar:file,birth_date:date,phone:string,website:url"
```

## Customization

### Modifying Templates
Edit the template files in `resources/templates/` to customize the generated code:
- Add new field types
- Modify styling
- Change component structure
- Add custom validation rules

### Adding New Field Types
1. Add the field type to `$availableFieldTypes` in the command
2. Update the migration template to handle the new type
3. Update React templates to render appropriate inputs
4. Add validation rules in the controller template

## Troubleshooting

### Common Issues
1. **Storage Link**: Ensure `php artisan storage:link` is run
2. **File Permissions**: Check storage directory permissions
3. **Template Errors**: Verify template syntax and placeholders
4. **Migration Errors**: Check if table already exists

### Debugging
- Check generated files for syntax errors
- Verify template placeholder replacements
- Review Laravel logs for errors
- Test individual components

## Contributing

To improve the generator:
1. Update templates in `resources/templates/`
2. Modify the `CreateCrud` command logic
3. Add new field types and validation rules
4. Enhance React component functionality

## License

This CRUD generator is part of the Laravel React application and follows the same license terms. 