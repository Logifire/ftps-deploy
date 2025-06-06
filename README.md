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

## Requirements

- PHP 8.0 or higher
- PHP FTP extension
- FTPS-enabled hosting

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

---
*Note: This library, including its code, documentation, and this README, was generated with the assistance of AI.*