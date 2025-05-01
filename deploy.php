<?php

namespace Deploy;

class Deployer
{
    private const CONNECTION_TIMEOUT_SECONDS = 5;
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
        
        echo "Found " . count($this->changedFiles) . " files to upload:\n";
        foreach ($this->changedFiles as $file) {
            echo "  - $file\n";
        }
        echo "\n";
        
        if (!$this->confirmDeployment()) {
            echo "Deployment cancelled.\n";
            return;
        }
        
        $this->connectFTPS();
        $this->uploadChangedFiles();
        $this->saveHashes();
        $this->disconnect();
        
        echo "Deployment completed successfully.\n";
    }

    private function confirmDeployment(): bool
    {
        echo "Do you want to proceed with the deployment? [Y/n] ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        return empty($line) || strtolower($line) === 'y';
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
        
        $this->conn = ftp_ssl_connect($this->host, $this->port, self::CONNECTION_TIMEOUT_SECONDS);
        if (!$this->conn) {
            throw new \Exception(sprintf(
                "Could not establish FTPS connection to %s:%d (timeout: %ds)",
                $this->host,
                $this->port,
                self::CONNECTION_TIMEOUT_SECONDS
            ));
        }
        
        $login = ftp_login($this->conn, $this->username, $this->password);
        if (!$login) {
            throw new \Exception(sprintf(
                "FTPS authentication failed for user '%s' on %s:%d",
                $this->username,
                $this->host,
                $this->port
            ));
        }

        // Set timeout for subsequent operations
        stream_set_timeout($this->conn, self::CONNECTION_TIMEOUT_SECONDS);
        
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

    public static function initializeConfig(string $targetDir): void 
    {
        $templatePath = __DIR__ . '/deploy-config.php';
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
}

// Run the deployment when executed from command line
if (PHP_SAPI === 'cli') {
    $workingDir = getcwd();
    
    // Handle --init flag
    if (isset($argv[1]) && $argv[1] === 'init') {
        Deployer::initializeConfig($workingDir);
        exit(0);
    }
    
    $configFile = $workingDir . '/deploy-config.php';
    
    if (!file_exists($configFile)) {
        die("Config file 'deploy-config.php' not found in {$workingDir}\n" .
            "Run with --init to create a template configuration file.\n");
    }
    
    $config = require $configFile;
    
    try {
        $deployer = new Deployer(
            host: $config['host'],
            username: $config['username'],
            password: $config['password'],
            localBasePath: $config['local_path'] ?? $workingDir,
            remoteBasePath: $config['remote_path'],
            port: $config['port'] ?? 21,
            ignoredPatterns: $config['ignore_patterns'] ?? []
        );
        
        $deployer->deploy();
    } catch (\Exception $e) {
        die("Deployment error: " . $e->getMessage() . "\n");
    }
}