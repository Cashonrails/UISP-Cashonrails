#!/usr/bin/env php
<?php

/**
 * Plugin installation script
 */

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function to track install progress
function logInstall($message) {
    $logFile = __DIR__ . '/data/install.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    echo "$message\n";
}

// Create data directory for logs and plugin data
$dataPath = __DIR__ . '/data';
if (!file_exists($dataPath)) {
    if (mkdir($dataPath, 0755, true)) {
        logInstall("Created data directory at $dataPath");
    } else {
        logInstall("ERROR: Failed to create data directory at $dataPath");
    }
}

// Log the installation start
logInstall("Starting installation process");
logInstall("Current directory: " . __DIR__);

// Check if composer is available
$composerPath = trim(shell_exec('which composer 2>/dev/null') ?: '');
logInstall("Composer path: " . ($composerPath ?: "Not found"));

// Check if composer.json exists
if (!file_exists(__DIR__ . '/composer.json')) {
    logInstall("ERROR: composer.json not found");
    exit(1);
}
logInstall("composer.json found");

// Try to install dependencies with composer
$vendorPath = __DIR__ . '/vendor';
$autoloadPath = $vendorPath . '/autoload.php';

if (!file_exists($autoloadPath)) {
    logInstall("Vendor directory not found, attempting to install dependencies");
    
    // Create a temp composer.phar if needed
    if (!$composerPath) {
        logInstall("Downloading composer.phar");
        $composerInstalled = false;
        
        // Try to download composer
        if (function_exists('curl_exec')) {
            $curl = curl_init("https://getcomposer.org/composer-stable.phar");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $composerPhar = curl_exec($curl);
            
            if ($composerPhar && file_put_contents(__DIR__ . '/composer.phar', $composerPhar)) {
                chmod(__DIR__ . '/composer.phar', 0755);
                $composerPath = __DIR__ . '/composer.phar';
                $composerInstalled = true;
                logInstall("Downloaded composer.phar successfully");
            } else {
                logInstall("ERROR: Failed to download composer with curl");
            }
        } else {
            logInstall("ERROR: curl not available to download composer");
        }
        
        if (!$composerInstalled) {
            logInstall("ERROR: Could not install composer");
        }
    }
    
    // Now install dependencies with composer
    if ($composerPath) {
        $command = "cd " . escapeshellarg(__DIR__) . " && $composerPath install --no-dev";
        logInstall("Running: $command");
        
        exec($command . " 2>&1", $output, $returnCode);
        logInstall("Composer output: " . implode("\n", $output));
        
        if ($returnCode !== 0) {
            logInstall("ERROR: Composer install failed with code $returnCode");
        } else {
            logInstall("Composer dependencies installed successfully");
        }
    } else {
        // Manual include of UCRM SDK
        logInstall("WARNING: Composer not available, attempting manual setup");
        
        if (!file_exists($vendorPath)) {
            mkdir($vendorPath, 0755, true);
            logInstall("Created vendor directory");
        }
        
        // Try to use the built-in UCRM SDK
        if (file_exists('/usr/local/etc/ucrm/vendor/ubnt/ucrm-plugin-sdk')) {
            logInstall("Copying UCRM SDK from system location");
            
            if (!file_exists($vendorPath . '/ubnt')) {
                mkdir($vendorPath . '/ubnt', 0755, true);
            }
            
            // Simple recursive directory copy function
            function copyDir($src, $dst) {
                $dir = opendir($src);
                @mkdir($dst);
                while (($file = readdir($dir)) !== false) {
                    if ($file != '.' && $file != '..') {
                        if (is_dir($src . '/' . $file)) {
                            copyDir($src . '/' . $file, $dst . '/' . $file);
                        } else {
                            copy($src . '/' . $file, $dst . '/' . $file);
                        }
                    }
                }
                closedir($dir);
            }
            
            copyDir('/usr/local/etc/ucrm/vendor/ubnt/ucrm-plugin-sdk', $vendorPath . '/ubnt/ucrm-plugin-sdk');
            
            // Create a basic autoloader
            $autoloaderContent = '<?php
            spl_autoload_register(function ($class) {
                $prefix = "Ubnt\\\\UcrmPluginSdk\\\\";
                $baseDir = __DIR__ . "/ubnt/ucrm-plugin-sdk/src/";
                
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }
                
                $relativeClass = substr($class, $len);
                $file = $baseDir . str_replace("\\\\", "/", $relativeClass) . ".php";
                
                if (file_exists($file)) {
                    require $file;
                }
            });';
            
            file_put_contents($autoloadPath, $autoloaderContent);
            logInstall("Created basic autoloader");
        } else {
            logInstall("ERROR: UCRM SDK not found in system location");
        }
    }
}

// Create the public.php symlink if it doesn't exist and isn't in place
if (!file_exists(__DIR__ . '/public.php')) {
    logInstall("Creating public.php file");
    if (file_exists(__DIR__ . '/src/public.php')) {
        copy(__DIR__ . '/src/public.php', __DIR__ . '/public.php');
        logInstall("Copied public.php from src directory");
    } else {
        // If there's no src/public.php, we already have a public.php in the root
        logInstall("public.php already exists in root directory");
    }
}

logInstall("Installation completed");
