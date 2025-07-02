<?php

namespace Deploy;

class ChangedFilesDetector
{
    private string $localBasePath;
    private array $ignoredPatterns;
    private array $pathMappings;
    private string $hashFile;
    private array $previousFileHashes = [];
    private array $fileHashes = [];
    private array $changedFiles = [];
    private array $deletedFiles = [];

    public function __construct(
        string $localBasePath,
        array $ignoredPatterns = [],
        array $pathMappings = [],
        string $hashFile = '.deploy-hashes.json'
    ) {
        $this->localBasePath = rtrim($localBasePath, '/');
        $this->ignoredPatterns = $ignoredPatterns;
        $this->pathMappings = $pathMappings;
        $this->hashFile = $hashFile;
    }

    /**
     * Generates the hash file from the current file structure, without marking any files as changed or deleted.
     * Returns an array with 'hashes' and saves them to the hash file.
     */
    public function generateHashesOnly(): array
    {
        $this->previousFileHashes = [];
        $this->fileHashes = [];
        $this->changedFiles = [];
        $this->deletedFiles = [];
        $this->detectChangedFiles();
        file_put_contents($this->hashFile, json_encode($this->fileHashes, JSON_PRETTY_PRINT));
        return [
            'hashes' => $this->fileHashes,
        ];
    }

    public function detect(): array
    {
        $this->loadPreviousHashes();
        $this->detectChangedFiles();
        $this->detectDeletedFiles();
        return [
            'changed' => $this->changedFiles,
            'deleted' => $this->deletedFiles,
            'hashes' => $this->fileHashes,
        ];
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
        $fullDir = $this->localBasePath . ($dir ? '/' . $dir : '');
        $files = scandir($fullDir);
        if ($files === false) return;
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
            // If pathMappings is empty, include all files (except ignored)
            $matchesMapping = empty($this->pathMappings);
            if (!$matchesMapping) {
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

    /**
     * Save the current file hashes to the hash file.
     */
    public function saveHashesToFile(): void
    {
        file_put_contents($this->hashFile, json_encode($this->fileHashes, JSON_PRETTY_PRINT));
    }

    /**
     * Remove a file from the hashes and update the hash file.
     */
    public function removeFileHash(string $file): void
    {
        unset($this->fileHashes[$file]);
        $this->saveHashesToFile();
    }
}
