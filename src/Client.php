<?php

namespace Deploy;

class Client
{
    private const CONNECTION_TIMEOUT_SECONDS = 5;
    private array $changedFiles = [];
    private array $deletedFiles = [];
    private ChangedFilesDetector $detector;
    private $conn;

    private string $localBasePath;
    private string $remoteBasePath;

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private int $port = 21,
        private array $ignoredPatterns = [],
        private array $pathMappings = [],
        string $hashFile = '.deploy-hashes.json'
    ) {
        $this->localBasePath = rtrim(getcwd(), '/');
        $this->remoteBasePath = '/';
        $this->detector = new ChangedFilesDetector(
            $this->localBasePath,
            $this->ignoredPatterns,
            $this->pathMappings,
            $hashFile
        );
    }

    /**
     * Deploys files or only generates hashes if $onlyHashes is true.
     * @param bool $onlyHashes If true, only generate/update the hash file and do not deploy anything.
     */
    public function deploy(bool $onlyHashes = false): void
    {
        if ($onlyHashes) {
            $result = $this->detector->generateHashesOnly();
            echo "Hash file generated/updated with " . count($result['hashes']) . " entries.\n";
            return;
        }

        $result = $this->detector->detect();
        $this->changedFiles = $result['changed'];
        $this->deletedFiles = $result['deleted'];

        if (empty($this->changedFiles) && empty($this->deletedFiles)) {
            echo "No files need to be uploaded or deleted.\n";
            return;
        }
        if (!empty($this->changedFiles)) {
            echo "Found " . count($this->changedFiles) . " files to upload:\n";
            foreach ($this->changedFiles as $file) {
                echo "  + $file\n";
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
        $deleteErrors = $this->deleteRemovedFilesWithErrors();
        $uploadErrors = $this->uploadChangedFilesWithErrors();
        $this->detector->saveHashesToFile();
        $this->disconnect();

        if (empty($deleteErrors) && empty($uploadErrors)) {
            echo "Deployment completed successfully.\n";
        } else {
            echo "Deployment completed with errors.\n";
            if (!empty($deleteErrors)) {
                echo "Failed to delete the following files:\n";
                foreach ($deleteErrors as $file) {
                    echo "  - $file\n";
                }
            }
            if (!empty($uploadErrors)) {
                echo "Failed to upload the following files:\n";
                foreach ($uploadErrors as $file) {
                    echo "  + $file\n";
                }
            }
        }
    }

    private function confirmDeployment(): bool
    {
        echo "Do you want to proceed with the deployment? [Y/n] ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        return empty($line) || strtolower($line) === 'y';
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
    
    private function uploadChangedFilesWithErrors(): array
    {
        $errors = [];
        foreach ($this->changedFiles as $file) {
            $localFile = $this->localBasePath . '/' . $file;
            $remoteFile = $this->getRemotePath($file);
            $this->ensureRemoteDirectory(dirname($remoteFile));
            echo "Uploading: $file to $remoteFile\n";
            if (!ftp_put($this->conn, $remoteFile, $localFile, FTP_BINARY)) {
                echo "Failed to upload $file\n";
                $errors[] = $file;
            }
        }
        return $errors;
    }

    private function deleteRemovedFilesWithErrors(): array
    {
        $errors = [];
        foreach ($this->deletedFiles as $file) {
            $remoteFile = $this->getRemotePath($file);
            echo "Deleting remote file: $remoteFile\n";
            if (!@ftp_delete($this->conn, $remoteFile)) {
                echo "Failed to delete $remoteFile\n";
                $errors[] = $file;
            }
            $this->detector->removeFileHash($file);
        }
        return $errors;
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