<?php

$autoloadFile = dirname(__DIR__, 3).'/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    $autoloadFile = dirname(__DIR__).'/vendor/autoload.php';
}

require $autoloadFile;

// Clean up temp directories
$tempDir = sys_get_temp_dir() . '/doctrine_dedicated_connection_bundle';
if (is_dir($tempDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $path) {
        try {
            $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }
}
