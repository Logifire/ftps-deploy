<?php

namespace Deploy;

class Client
{
    private const CONNECTION_TIMEOUT_SECONDS = 5;
    private string $hashFile = '.deploy-hashes.json';
    private array $fileHashes = [];
    private array $previousFileHashes = [];
    private array $changedFiles = [];
    private array $deletedFiles = [];
    private $conn;

    private string $localBasePath;
    private string $remoteBasePath;

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private int $port = 21,
        private array $ignoredPatterns = [],
        private array $pathMappings = []
    ) {
        $this->localBasePath = rtrim(getcwd(), '/');
        $this->remoteBasePath = '/';
    }

    public function deploy(): void
    {
        $this->loadPreviousHashes();
        $this->detectChangedFiles();
        $this->detectDeletedFiles();
        
        if (empty($this->changedFiles) && empty($this->deletedFiles)) {
            echo "No files need to be uploaded or deleted.\n";
            return;
        }
        if (!empty($this->changedFiles)) {
            echo "Found " . count($this->changedFiles) . " files to upload:\n";
            foreach ($this->changedFiles as $file) {
                echo "  - $file\n";
            }
            echo "\n";
        }
        if (!empty($this->deletedFiles)) {
            echo "Found " . count($this->deletedFiles) . " files to delete:\n";
            foreach ($this->deletedFiles as $file) {
                echo "  - $file\n";
            }
            echo "\n";
        }
        
        if (!$this->confirmDeployment()) {
            echo "Deployment cancelled.\n";
            return;
        }
        
        $this->connectFTPS();
        $this->deleteRemovedFiles();
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
            $this->previousFileHashes = json_decode(file_get_contents($this->hashFile), true) ?: [];
        }
        $this->fileHashes = [];
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

            // Only consider files that match pathMappings (exact or pattern)
            $matchesMapping = false;
            if (isset($this->pathMappings[$relativePath])) {
                $matchesMapping = true;
            } else {
                foreach (array_keys($this->pathMappings) as $pattern) {
                    if (fnmatch($pattern, $relativePath)) {
                        $matchesMapping = true;
                        break;
                    }
                }
            }
            if (!$matchesMapping && !is_dir($fullPath)) {
                continue;
            }

            if (is_dir($fullPath)) {
                $this->detectChangedFiles($relativePath);
            } else {
                $currentHash = md5_file($fullPath);
                if (!isset($this->previousFileHashes[$relativePath]) || $this->previousFileHashes[$relativePath] !== $currentHash) {
                    $this->changedFiles[] = $relativePath;
                }
                $this->fileHashes[$relativePath] = $currentHash;
            }
        }
    }
    
    private function detectDeletedFiles(): void
    {
        $deleted = array_diff(array_keys($this->previousFileHashes), array_keys($this->fileHashes));
        $this->deletedFiles = $deleted;
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

        // Enable passive mode
        ftp_pasv($this->conn, true);
        echo "Connected successfully.\n";
    }
    
    private function uploadChangedFiles(): void
    {
        foreach ($this->changedFiles as $file) {
            $localFile = $this->localBasePath . '/' . $file;
            $remoteFile = $this->getRemotePath($file);
            
            // Create directory structure if needed
            $this->ensureRemoteDirectory(dirname($remoteFile));
            
            echo "Uploading: $file to $remoteFile\n";
            if (!ftp_put($this->conn, $remoteFile, $localFile, FTP_BINARY)) {
                echo "Failed to upload $file\n";
            }
        }
    }
    
    private function deleteRemovedFiles(): void
    {
        foreach ($this->deletedFiles as $file) {
            $remoteFile = $this->getRemotePath($file);
            echo "Deleting remote file: $remoteFile\n";
            if (!@ftp_delete($this->conn, $remoteFile)) {
                echo "Failed to delete $remoteFile\n";
            }
            unset($this->fileHashes[$file]);
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
            // Suppress SSL_read warning on shutdown (common with FTPS servers)
            @ftp_close($this->conn);
        }
    }

    private function getRemotePath(string $localFile): string 
    {
        // Check for exact file matches first
        if (isset($this->pathMappings[$localFile])) {
            return $this->pathMappings[$localFile];
        }

        // Then check for pattern matches
        foreach ($this->pathMappings as $pattern => $remotePath) {
            if (fnmatch($pattern, $localFile)) {
                // Replace the matching part with the remote path
                $relativePath = ltrim(substr($localFile, strlen(dirname($pattern))), '/');
                return rtrim($remotePath, '/') . '/' . $relativePath;
            }
        }

        // Default to the standard remote path if no mapping found
        return $this->remoteBasePath . '/' . $localFile;
    }
}