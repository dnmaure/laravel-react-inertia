# Toast Notifications Usage Guide

This application now includes toast notifications powered by Sonner. Toasts provide user feedback for actions like form submissions, data operations, and error handling.

## Setup

The toast functionality is already integrated into your application:

- **ToastProvider**: Added to both `AuthenticatedLayout` and `GuestLayout`
- **Toast Component**: Located at `resources/js/Components/ui/toast.jsx`
- **Utility Functions**: Located at `resources/js/lib/toast.js`

## Usage

### Basic Usage

Import the toast utility in your component:

```javascript
import { showToast } from '@/lib/toast';
```

### Available Toast Types

#### Success Toast
```javascript
showToast.success('Operation completed successfully!');
```

#### Error Toast
```javascript
showToast.error('Something went wrong. Please try again.');
```

#### Warning Toast
```javascript
showToast.warning('Please check your input before proceeding.');
```

#### Info Toast
```javascript
showToast.info('Here is some information for you.');
```

#### Loading Toast
```javascript
const loadingToast = showToast.loading('Processing your request...');
// Later, dismiss the loading toast
showToast.dismiss(loadingToast);
```

### Form Integration Examples

#### Create Form
```javascript
const submit = (e) => {
    e.preventDefault();
    post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast.success('User created successfully!');
        },
        onError: (errors) => {
            showToast.error('Failed to create user. Please check the form and try again.');
        },
    });
};
```

#### Update Form
```javascript
const submit = (e) => {
    e.preventDefault();
    put(route('users.update', user.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast.success('User updated successfully!');
        },
        onError: (errors) => {
            showToast.error('Failed to update user. Please check the form and try again.');
        },
    });
};
```

#### Delete Operation
```javascript
const handleDelete = () => {
    if (confirm('Are you sure you want to delete this item?')) {
        router.delete(route('users.destroy', user.id), {
            onSuccess: () => {
                showToast.success('User deleted successfully!');
            },
            onError: () => {
                showToast.error('Failed to delete user. Please try again.');
            },
        });
    }
};
```

### Advanced Usage

#### Custom Duration
```javascript
showToast.success('Custom duration message', {
    duration: 8000, // 8 seconds
});
```

#### Custom Styling
```javascript
showToast.success('Custom styled message', {
    style: {
        background: 'linear-gradient(to right, #4f46e5, #7c3aed)',
        color: 'white',
    },
});
```

#### With Loading States
```javascript
const submit = async (e) => {
    e.preventDefault();
    
    const loadingToast = showToast.loading('Saving your changes...');
    
    try {
        await post(route('users.store'));
        showToast.dismiss(loadingToast);
        showToast.success('User created successfully!');
    } catch (error) {
        showToast.dismiss(loadingToast);
        showToast.error('Failed to create user.');
    }
};
```

## Template Integration

The CRUD generator templates have been updated to include toast notifications:

- **Create templates**: Show success/error toasts on form submission
- **Edit templates**: Show success/error toasts on form submission  
- **Index templates**: Show success/error toasts on delete operations

## Configuration

The toast configuration is set in `resources/js/Components/ui/toast.jsx`:

- **Position**: Top-right corner
- **Styling**: Matches your application's theme
- **Dark mode**: Automatically adapts to dark mode

## Best Practices

1. **Be Specific**: Use descriptive messages that tell users exactly what happened
2. **Keep it Short**: Toast messages should be concise and to the point
3. **Use Appropriate Types**: 
   - `success` for completed operations
   - `error` for failures and errors
   - `warning` for potential issues
   - `info` for general information
4. **Handle Loading States**: Use loading toasts for operations that take time
5. **Consistent Messaging**: Use consistent language across your application

## Examples in Your Codebase

Check these files for real examples:
- `resources/js/Pages/Profile/Partials/UpdatePasswordForm.jsx`
- `resources/templates/react_create.stub`
- `resources/templates/react_edit.stub`
- `resources/templates/columns.stub` 