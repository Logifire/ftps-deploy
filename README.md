# FTPS Deploy

A simple PHP deployment tool that uses FTPS to upload changed files to a remote server. It tracks file changes using MD5 hashes and only uploads modified files.

## Features

- FTPS secure file transfer
- Tracks file changes using MD5 hashes
- Only uploads modified files
- Supports file/directory ignore patterns
- Creates remote directories automatically
- Passive mode FTP support

## Installation

Install via Composer:

```bash
composer require logifire/ftps-deploy
```

## Configuration

You can initialize a template configuration file by running:

```bash
vendor/bin/deploy.php init
```

This will create a `deploy-config.php` file in your project root which you can then edit with your FTPS credentials.

Otherwise, create a `deploy-config.php` file manually in your project root:

```php
<?php
return [
    // FTP connection settings
    'host' => 'example.com',
    'port' => 21,
    'username' => 'your-username',
    'password' => 'your-password',
    
    // Files/folders to ignore (supports wildcards)
    'ignore_patterns' => [
        '.git',
        '.gitignore',
        'node_modules',
        'vendor',
        '*.log',
        'tests'
    ]
];
```

## Custom Configuration File Location

By default, `deploy.php` looks for `deploy-config.php` in the current working directory. If you want to use a configuration file located elsewhere, you can specify its path using the `--config` option:

```bash
vendor/bin/deploy.php --config=path/to/your/deploy-config.php
```

This allows you to keep multiple configuration files for different environments or projects, and select which one to use at deploy time.

## Usage

Run deployment using:

```bash
vendor/bin/deploy.php
```

You can also specify a custom config file location with the `--config` argument:

```bash
vendor/bin/deploy.php --config=path/to/deploy-config.php
```

## Composer Script (Optional)

If you are using Composer, you can add a convenient script to your `composer.json`:

```json
"scripts": {
    "deploy": "php vendor/bin/deploy.php"
}
```

This allows you to run deployment with:

```bash
composer deploy
```

## Deployment Hashes File (`deploy-hashes.json`)

FTPS Deploy keeps track of file changes using a JSON file (by default named `.deploy-hashes.json`) in your project directory. This file stores hashes of previously deployed files to determine which files have changed and need to be uploaded or deleted.

- **Default location:** `.deploy-hashes.json` in your project root.
- **Custom location:** The `hash_file` entry is present by default in your `deploy-config.php` file. You can change its value to specify a different path:

```php
'hash_file' => '/path/to/your-hashes.json',
```

This is useful if you want to:
- Store deployment state outside your project directory (e.g., in CI/CD pipelines like GitHub Actions)
- Use different hash files for different environments
- Avoid committing the hash file to version control

If you change the location, make sure the path is writable by the deployment process.

## Requirements

- PHP 8.0 or higher
- PHP FTP extension
- FTPS-enabled hosting

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

---
*Note: This library, including its code, documentation, and this README, was generated with the assistance of AI.*