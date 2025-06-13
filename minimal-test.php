<?php
/**
 * Minimal test to isolate the "Client ID is required" error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create data directory if needed
$dataDir = __DIR__ . '/data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

echo "<h1>Minimal Payment Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .error{color:red;} .success{color:green;} .info{color:blue;}</style>";

// Test 1: Check what happens when we POST to initialize-payment.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_initialize'])) {
    
    echo "<h2>Testing initialize-payment.php directly...</h2>";
    
    // Prepare test data
    $testData = [
        'clientId' => '1',
        'invoiceIds' => '123',
        'amount' => '1000.00',
        'email' => 'test@example.com'
    ];
    
    echo "<div class='info'>Sending POST data: " . print_r($testData, true) . "</div>";
    
    // Simulate the POST request to initialize-payment.php
    $postData = http_build_query($testData);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData
        ]
    ]);
    
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/initialize-payment.php';
    echo "<div class='info'>Calling URL: $url</div>";
    
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        echo "<div class='error'>Failed to call initialize-payment.php</div>";
        $error = error_get_last();
        if ($error) {
            echo "<div class='error'>Error: " . $error['message'] . "</div>";
        }
    } else {
        echo "<div class='success'>Response received:</div>";
        echo "<pre>" . htmlspecialchars($result) . "</pre>";
    }
}

// Test 2: Check what main.php processRequest function does
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_main'])) {
    echo "<h2>Testing main.php processRequest function...</h2>";
    
    // Include required files
    try {
        require_once __DIR__ . '/public.php';
        require_once __DIR__ . '/main.php';
        
        echo "<div class='success'>Files loaded successfully</div>";
        
        // Test the processRequest function directly
        $testRequestData = [
            'action' => 'pay',
            'clientId' => 1,
            'invoiceIds' => '123',
            'amount' => 1000.00,
            'email' => 'test@example.com'
        ];
        
        echo "<div class='info'>Testing with data: " . print_r($testRequestData, true) . "</div>";
        
        // Check if pluginOptions is available
        global $pluginOptions;
        if (empty($pluginOptions)) {
            echo "<div class='error'>Plugin options not loaded</div>";
        } else {
            echo "<div class='success'>Plugin options available</div>";
        }
        
        // Call processRequest function
        ob_start();
        try {
            processRequest($pluginOptions ?: [], $testRequestData);
            echo "<div class='success'>processRequest completed without errors</div>";
        } catch (Exception $e) {
            echo "<div class='error'>processRequest threw exception: " . $e->getMessage() . "</div>";
        }
        $output = ob_get_clean();
        
        if (!empty($output)) {
            echo "<div class='info'>Function output:</div>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>Error loading files: " . $e->getMessage() . "</div>";
    }
}

// Test 3: Manual form submission test
echo "<h2>Manual Tests</h2>";

echo "<h3>Test 1: Call initialize-payment.php</h3>";
echo "<form method='POST'>";
echo "<input type='hidden' name='test_initialize' value='1'>";
echo "<button type='submit'>Test initialize-payment.php</button>";
echo "</form>";

echo "<h3>Test 2: Test main.php processRequest</h3>";
echo "<form method='POST'>";
echo "<input type='hidden' name='test_main' value='1'>";
echo "<button type='submit'>Test main.php processRequest</button>";
echo "</form>";

echo "<h3>Test 3: Direct form submission</h3>";
echo "<form method='POST' action='initialize-payment.php' target='_blank'>";
echo "<input type='hidden' name='clientId' value='1'>";
echo "<input type='hidden' name='invoiceIds' value='123'>";
echo "<input type='hidden' name='amount' value='1000'>";
echo "<input type='hidden' name='email' value='test@example.com'>";
echo "<button type='submit'>Direct Submit to initialize-payment.php</button>";
echo "</form>";

// Show current request details
echo "<h2>Current Request Details</h2>";
echo "<div class='info'>";
echo "<strong>REQUEST_METHOD:</strong> " . ($_SERVER['REQUEST_METHOD'] ?? 'Not set') . "<br>";
echo "<strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "<br>";
echo "<strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "<br>";
echo "<strong>SCRIPT_NAME:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "<br>";
echo "<strong>GET:</strong> " . print_r($_GET, true) . "<br>";
echo "<strong>POST:</strong> " . print_r($_POST, true) . "<br>";
echo "</div>";

// Check log files
echo "<h2>Recent Logs</h2>";
$logFiles = ['input_debug.log', 'validation_error.log', 'payment_error.log'];
foreach ($logFiles as $logFile) {
    $logPath = $dataDir . '/' . $logFile;
    if (file_exists($logPath)) {
        echo "<h3>$logFile</h3>";
        $content = file_get_contents($logPath);
        $lines = array_filter(explode("\n", $content));
        $recentLines = array_slice($lines, -10);
        echo "<pre>" . htmlspecialchars(implode("\n", $recentLines)) . "</pre>";
    } else {
        echo "<div class='info'>$logFile - No log file found</div>";
    }
}
?>
