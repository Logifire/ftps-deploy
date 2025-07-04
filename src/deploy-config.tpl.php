<?php
// Configuration for FTPS deployment

return [
    // FTP connection settings
    'host' => 'example.com',
    'port' => 21,
    'username' => 'your-username',
    'password' => 'your-password',
    
    // Define where specific paths should be uploaded to
    // Format: 'local-pattern' => 'remote-path'
    'path_mappings' => [
        // Upload public files to public_html
        // 'public/*' => '/public_html',
        
        // Upload src folder to private_html/src
        // 'src/*' => '/private_html/src',
        
        // Upload config files to private_html/config
        // 'config/*' => '/private_html/config',
        
        // Upload individual files to specific locations
        // 'index.php' => '/public_html/index.php'
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
    ],

    // Path to the file where deployment hashes are stored (optional)
    'hash_file' => '.deploy-hashes.json',
];
