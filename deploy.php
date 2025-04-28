<?php

namespace Deploy;

class Deployer
{
    private string $hashFile = '.deploy-hashes.json';
    private array $fileHashes = [];
    private array $changedFiles = [];
    private $conn;

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private string $localBasePath,
        private string $remoteBasePath,
        private int $port = 21,
        private array $ignoredPatterns = []
    ) {
        // Clean up paths
        $this->localBasePath = rtrim($localBasePath, '/');
        $this->remoteBasePath = rtrim($remoteBasePath, '/');
        
        // Set default ignore patterns if none provided
        if (empty($this->ignoredPatterns)) {
            $this->ignoredPatterns = [
                '.git', 
                'node_modules', 
                'vendor', 
                '.deploy-hashes.json',
                '*.log',
                'tests'
            ];
        }
    }

    public function deploy(): void
    {
        $this->loadPreviousHashes();
        $this->detectChangedFiles();
        
        if (empty($this->changedFiles)) {
            echo "No files need to be uploaded.\n";
            return;
        }
        
        echo "Found " . count($this->changedFiles) . " files to upload.\n";
        
        $this->connectFTPS();
        $this->uploadChangedFiles();
        $this->saveHashes();
        $this->disconnect();
        
        echo "Deployment completed successfully.\n";
    }

    private function loadPreviousHashes(): void
    {
        if (file_exists($this->hashFile)) {
            $this->fileHashes = json_decode(file_get_contents($this->hashFile), true) ?: [];
        }
    }

    private function detectChangedFiles(string $dir = ''): void
    {
        $fullDir = $this->localBasePath . '/' . $dir;
        $files = scandir($fullDir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $relativePath = $dir ? "$dir/$file" : $file;
            $fullPath = "$fullDir/$file";
            
            // Check if path should be ignored
            foreach ($this->ignoredPatterns as $pattern) {
                if (fnmatch($pattern, $relativePath) || fnmatch("*/$pattern", $relativePath)) {
                    continue 2;
                }
            }
            
            if (is_dir($fullPath)) {
                $this->detectChangedFiles($relativePath);
            } else {
                $currentHash = md5_file($fullPath);
                if (!isset($this->fileHashes[$relativePath]) || $this->fileHashes[$relativePath] !== $currentHash) {
                    $this->changedFiles[] = $relativePath;
                    $this->fileHashes[$relativePath] = $currentHash;
                }
            }
        }
    }
    
    private function connectFTPS(): void
    {
        echo "Connecting to FTPS server...\n";
        
        $this->conn = ftp_ssl_connect($this->host, $this->port);
        if (!$this->conn) {
            throw new \Exception("Could not connect to FTP server.");
        }
        
        $login = ftp_login($this->conn, $this->username, $this->password);
        if (!$login) {
            throw new \Exception("FTP login failed.");
        }
        
        // Enable passive mode
        ftp_pasv($this->conn, true);
        echo "Connected successfully.\n";
    }
    
    private function uploadChangedFiles(): void
    {
        foreach ($this->changedFiles as $file) {
            $localFile = $this->localBasePath . '/' . $file;
            $remoteFile = $this->remoteBasePath . '/' . $file;
            
            // Create directory structure if needed
            $this->ensureRemoteDirectory(dirname($remoteFile));
            
            echo "Uploading: $file\n";
            if (!ftp_put($this->conn, $remoteFile, $localFile, FTP_BINARY)) {
                echo "Failed to upload $file\n";
            }
        }
    }
    
    private function ensureRemoteDirectory(string $dir): void
    {
        // Skip if it's the main remote directory
        if ($dir == $this->remoteBasePath) {
            return;
        }
        
        // Try to change to directory to check if it exists
        if (@ftp_chdir($this->conn, $dir)) {
            // Change back to the parent directory
            ftp_cdup($this->conn);
            return;
        }
        
        // Create parent directory first
        $this->ensureRemoteDirectory(dirname($dir));
        
        // Now create this directory
        $dirName = basename($dir);
        if (!@ftp_mkdir($this->conn, $dirName)) {
            throw new \Exception("Could not create directory: $dirName");
        }
        
        // Change to parent directory after creating the new one
        ftp_cdup($this->conn);
    }
    
    private function saveHashes(): void
    {
        file_put_contents($this->hashFile, json_encode($this->fileHashes, JSON_PRETTY_PRINT));
    }
    
    private function disconnect(): void
    {
        if ($this->conn) {
            ftp_close($this->conn);
        }
    }
}

// Run the deployment when executed from command line
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    if (!file_exists('deploy-config.php')) {
        die("Config file 'deploy-config.php' not found.\n");
    }
    
    $config = require 'deploy-config.php';
    
    try {
        $deployer = new Deployer(
            host: $config['host'],
            username: $config['username'],
            password: $config['password'],
            localBasePath: $config['local_path'] ?? __DIR__,
            remoteBasePath: $config['remote_path'],
            port: $config['port'] ?? 21,
            ignoredPatterns: $config['ignore_patterns'] ?? []
        );
        
        $deployer->deploy();
    } catch (\Exception $e) {
        die("Deployment error: " . $e->getMessage() . "\n");
    }
}