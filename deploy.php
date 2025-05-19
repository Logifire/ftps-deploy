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

// Run the deployment when executed from command line
if (PHP_SAPI === 'cli') {
    $workingDir = getcwd();
    
    // Handle --init flag
    if (isset($argv[1]) && $argv[1] === 'init') {
        initializeConfig($workingDir);
        exit(0);
    }
    
    $configFile = $workingDir . '/deploy-config.php';
    
    if (!file_exists($configFile)) {
        die("Config file 'deploy-config.php' not found in {$workingDir}\n" .
            "Run with --init to create a template configuration file.\n");
    }
    
    $config = require $configFile;
    
    try {
        $client = new Client(
            host: $config['host'],
            username: $config['username'],
            password: $config['password'],
            localBasePath: $config['local_path'] ?? $workingDir,
            remoteBasePath: $config['remote_path'],
            port: $config['port'] ?? 21,
            ignoredPatterns: $config['ignore_patterns'] ?? []
        );
        
        $client->deploy();
    } catch (\Exception $e) {
        die("Deployment error: " . $e->getMessage() . "\n");
    }
}