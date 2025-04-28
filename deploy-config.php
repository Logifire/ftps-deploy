<?php
// deploy-config.php - Configuration for FTPS deployment

return [
    // FTP connection settings
    'host' => 'example.com',
    'port' => 21,
    'username' => 'your-username',
    'password' => 'your-password',
    
    // Local and remote paths
    'local_path' => __DIR__,  // Current directory - adjust if needed
    'remote_path' => '/public_html',  // Remote path on your shared host
    
    // Files/folders to ignore (supports wildcards)
    'ignore_patterns' => [
        '.git',
        '.gitignore',
        'node_modules',
        'vendor',
        '.deploy-hashes.json',
        'deploy-config.php',
        'deploy.php',
        '*.log',
        'tests'
    ]
];