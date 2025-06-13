<?php
/**
 * Public entry point for CashOnRails Payment Gateway Plugin
 * Handles autoloading, initialization, and request routing.
 */

// ========================
// 1. INITIAL SETUP
// ========================

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
        die('Failed to create data directory.');
    }
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', $dataDir . '/php_errors.log');

// ========================
// 2. SDK AND DEPENDENCIES
// ========================

$sdkLoaded = false;
$sdkPaths = [
    __DIR__ . '/vendor/autoload.php',          // Local development
    '/usr/local/etc/ucrm/vendor/autoload.php'  // UISP production
];

foreach ($sdkPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $sdkLoaded = true;
        break;
    }
}

if (!$sdkLoaded) {
    file_put_contents(
        $dataDir . '/init_error.log',
        date('[Y-m-d H:i:s] ') . "SDK not found in any of the searched paths.\n",
        FILE_APPEND
    );
    define('NO_SDK', true);
}

// ========================
// 3. GLOBAL INITIALIZATION
// ========================

global $api, $pluginOptions;
$api = null;
$pluginOptions = [];

try {
    if (!defined('NO_SDK')) {
        $api = \Ubnt\UcrmPluginSdk\Service\UcrmApi::create();

        $optionsManager = \Ubnt\UcrmPluginSdk\Service\UcrmOptionsManager::create();
        $pluginOptions = $optionsManager->loadOptions();

        file_put_contents(
            $dataDir . '/plugin_options.log',
            date('[Y-m-d H:i:s] ') . "Loaded options:\n" . print_r($pluginOptions, true) . "\n",
            FILE_APPEND
        );
    } else {
        file_put_contents(
            $dataDir . '/plugin_options.log',
            date('[Y-m-d H:i:s] ') . "SDK not available, using empty options.\n",
            FILE_APPEND
        );
    }
} catch (Throwable $e) {
    file_put_contents(
        $dataDir . '/init_error.log',
        date('[Y-m-d H:i:s] ') . "Initialization failed: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
}

// ========================
// 4. REQUEST LOGGING
// ========================

file_put_contents(
    $dataDir . '/request.log',
    date('[Y-m-d H:i:s] ') . "Incoming request:\n" .
    "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . "\n" .
    "GET: " . print_r($_GET, true) . "\n" .
    "POST: " . print_r($_POST, true) . "\n" .
    "Raw Input: " . file_get_contents('php://input') . "\n",
    FILE_APPEND
);

// ========================
// 5. INCLUDE MAIN LOGIC AND CALLBACK HANDLER
// ========================

require_once __DIR__ . '/main.php';

// ========================
// 6. ERROR HANDLING
// ========================

register_shutdown_function(function () use ($dataDir) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        file_put_contents(
            $dataDir . '/fatal_errors.log',
            date('[Y-m-d H:i:s] ') . "Fatal error:\n" . print_r($error, true) . "\n",
            FILE_APPEND
        );
    }
});

set_exception_handler(function (Throwable $e) use ($dataDir) {
    file_put_contents(
        $dataDir . '/exceptions.log',
        date('[Y-m-d H:i:s] ') . "Uncaught exception:\n" .
        "Message: " . $e->getMessage() . "\n" .
        "File: " . $e->getFile() . ":" . $e->getLine() . "\n" .
        "Trace:\n" . $e->getTraceAsString() . "\n",
        FILE_APPEND
    );

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An unexpected error occurred.']);
    exit;
});
