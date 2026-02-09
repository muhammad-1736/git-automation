# How it works & Helper Functions

## Overview

The Cherry Picker package now provides global helper functions (`config()`, `data_get()`, `env()`) similar to Laravel's helpers but working standalone with the PHP DI container.

## How It Works

### 1. Helper Functions

**File:** `src/helpers.php`

Three helper functions are provided:

```php
// Get a config value using dot notation
config('cherry-picker.git.username')        // Returns: 'your.username'
config('cherry-picker.gitlab.api_url')      // Returns: API URL

// Get all config
config()                                     // Returns: entire config array

// Get environment variable
env('GIT_USERNAME')                          // Returns: env var value
env('MISSING_VAR', 'default')                // Returns: default if not found

data_get('array.in.some.value')              // get data from array safely
```

### 2. Auto-Loading Helpers

The helpers are automatically loaded via composer's `files` autoload configuration:

```json
"autoload": {
    "psr-4": {"Mu\\CherryPicker\\": "src"},
    "files": ["src/helpers.php"]
}
```

This means helpers are available everywhere after `composer install`.

### 5. Initialization Flow

When you run the CLI or use the container:

1. **Container is created** with base path
2. **ConfigLoader.initialize()** is called:
   - Loads `.env` file using vlucas/phpdotenv
   - Loads all config files from `src/config/`
3. **Config is** singleton

### 6. Adding New Config

To add new configuration:

1. **Add to `.env`:**
```env
MY_NEW_VAR=value
```

2. **Add to `src/config/cherry-picker.php`:**
```php
return [
    'my_section' => [
        'my_key' => env('MY_NEW_VAR', 'default'),
    ],
    // ... other config
];
```

3. **Use in code:**
```php
$value = config('cherry-picker.my_section.my_key');
```