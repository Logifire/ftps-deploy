<?php
// deploy-config.tpl.php - Configuration template for FTPS deployment

return [
    // FTP connection settings
    'host' => 'example.com',
    'port' => 21,
    'username' => 'your-username',
    'password' => 'your-password',
    
    // Local and remote paths
    'local_path' => __DIR__,  // Current directory - adjust if needed
    'remote_path' => '/public_html',  // Base remote path for files without specific mapping
    
    // Define where specific paths should be uploaded to
    // Format: 'local-pattern' => 'remote-path'
    'path_mappings' => [
        // Upload public filer til public_html
        'public/*' => '/public_html',
        
        // Upload src mappe til private_html/src
        'src/*' => '/private_html/src',
        
        // Upload config filer til private_html/config
        'config/*' => '/private_html/config',
        
        // Upload enkelte filer til specifikke placeringer
        'index.php' => '/public_html/index.php'
    ],
    
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
        'tests',
        'composer.json',
        'LICENSE',
        'README.md'
    ]
];
