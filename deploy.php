#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Deploy\Client;

function initializeConfig(string $targetDir): void 
{
    $templatePath = __DIR__ . '/src/deploy-config.tpl.php';
    $targetPath = rtrim($targetDir, '/') . '/deploy-config.php';
    
    if (file_exists($targetPath)) {
        die("Configuration file already exists at: $targetPath\n");
    }
    
    if (!copy($templatePath, $targetPath)) {
        die("Failed to create configuration file at: $targetPath\n");
    }
    
    echo "Created configuration file: $targetPath\n";
    echo "Please edit the file and update your FTPS credentials before deploying.\n";
}

function printHelp(): void
{
    echo "Usage: deploy.php [init|--help] [--config=path]\n";
    echo "\nOptions:\n";
    echo "  init            Create a template deploy-config.php in the current directory.\n";
    echo "  --config=PATH   Use a custom config file instead of ./deploy-config.php.\n";
    echo "  --help          Show this help message.\n";
    echo "\n";
}

// Run the deployment when executed from command line
if (PHP_SAPI === 'cli') {
    $workingDir = getcwd();
    
    // Handle --help flag
    if (($argv[1] ?? '') === '--help') {
        printHelp();
        exit(0);
    }

    // Handle --init flag
    if (isset($argv[1]) && $argv[1] === 'init') {
        initializeConfig($workingDir);
        exit(0);
    }

    // Parse --config argument
    $configFile = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--config=')) {
            $configFile = substr($arg, 9);
            break;
        }
    }
    if (!$configFile) {
        $configFile = $workingDir . '/deploy-config.php';
    } elseif (!is_file($configFile)) {
        die("Config file not found at: {$configFile}\n");
    }

    if (!file_exists($configFile)) {
        die("Config file 'deploy-config.php' not found in {$workingDir}\n" .
            "Run with \"init\" to create a template configuration file.\n");
    }
    
    $config = require $configFile;
    
    try {
        $client = new Client(
            host: $config['host'],
            username: $config['username'],
            password: $config['password'],
            port: $config['port'] ?? 21,
            ignoredPatterns: $config['ignore_patterns'] ?? [],
            pathMappings: $config['path_mappings'] ?? [],
            hashFile: $config['hash_file'] ?? '.deploy-hashes.json'
        );
        
        $client->deploy();
    } catch (\Exception $e) {
        die("Deployment error: " . $e->getMessage() . "\n");
    }
}