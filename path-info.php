<?php
// Display server information to help diagnose paths
echo "<h2>Server Path Information</h2>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p>PHP Self: " . $_SERVER['PHP_SELF'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

echo "<h2>Environment Variables</h2>";
echo "<p>UCRM_PUBLIC_URL: " . getenv('UCRM_PUBLIC_URL') . "</p>";
echo "<p>UCRM_PLUGIN_ROOT_URL: " . (getenv('UCRM_PLUGIN_ROOT_URL') ?: 'Not defined') . "</p>";

// Try to figure out plugin URL
echo "<h2>Possible Plugin URLs</h2>";
$possible_urls = [
    getenv('UCRM_PUBLIC_URL') . '/api/v1.0/plugins/cashonrails-payment-gateway',
    getenv('UCRM_PUBLIC_URL') . '/plugins/cashonrails-payment-gateway',
    getenv('UCRM_PUBLIC_URL') . '/crm/api/v1.0/plugins/cashonrails-payment-gateway',
    getenv('UCRM_PUBLIC_URL') . '/crm/plugins/cashonrails-payment-gateway'
];

echo "<ul>";
foreach ($possible_urls as $url) {
    echo "<li><a href='{$url}' target='_blank'>{$url}</a></li>";
}
echo "</ul>";

// Get plugin manifest info
$manifest_file = __DIR__ . '/manifest.json';
if (file_exists($manifest_file)) {
    echo "<h2>Plugin Manifest</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents($manifest_file)) . "</pre>";
} else {
    echo "<p>Manifest file not found at: {$manifest_file}</p>";
}

// List files in current directory
echo "<h2>Files in Plugin Directory</h2>";
$files = scandir(__DIR__);
echo "<ul>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<li>{$file}</li>";
    }
}
echo "</ul>";
