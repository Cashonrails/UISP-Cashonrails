<?php
/**
 * Complete debug tool for cashonrails payment flow
 * This will help us identify exactly where the issue is occurring
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>cashonrails Payment Gateway Debug Tool</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.section { border: 1px solid #ddd; margin: 20px 0; padding: 15px; border-radius: 5px; }
.success { background-color: #d4edda; border-color: #c3e6cb; }
.error { background-color: #f8d7da; border-color: #f5c6cb; }
.warning { background-color: #fff3cd; border-color: #ffeaa7; }
.info { background-color: #d1ecf1; border-color: #bee5eb; }
pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
.test-form { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
</style>";

// 1. Check file structure
echo "<div class='section info'>";
echo "<h2>1. File Structure Check</h2>";
$files = [
    'public.php',
    'main.php', 
    'initialize-payment.php',
    'payment-form.php',
    'data/',
    'composer.json',
    'manifest.json'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    $class = $exists ? 'success' : 'error';
    echo "<div class='$class'>$file: " . ($exists ? 'EXISTS' : 'MISSING') . "</div>";
    
    if ($file === 'data/' && $exists) {
        $writable = is_writable($path);
        $class = $writable ? 'success' : 'error';
        echo "<div class='$class'>data/ writable: " . ($writable ? 'YES' : 'NO') . "</div>";
    }
}
echo "</div>";

// 2. Check PHP environment
echo "<div class='section info'>";
echo "<h2>2. PHP Environment</h2>";
echo "<div>PHP Version: " . PHP_VERSION . "</div>";
echo "<div>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</div>";
echo "<div>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</div>";
echo "<div>Current Directory: " . __DIR__ . "</div>";
echo "<div>cURL Available: " . (function_exists('curl_init') ? 'YES' : 'NO') . "</div>";
echo "</div>";

// 3. Test SDK Loading
echo "<div class='section info'>";
echo "<h2>3. UCRM SDK Test</h2>";
try {
    require_once __DIR__ . '/public.php';
    
    if (isset($api) && $api !== null) {
        echo "<div class='success'>✓ UCRM API loaded successfully</div>";
    } else {
        echo "<div class='error'>✗ UCRM API not loaded</div>";
    }
    
    if (isset($pluginOptions) && !empty($pluginOptions)) {
        echo "<div class='success'>✓ Plugin options loaded</div>";
        echo "<div>Available options: " . implode(', ', array_keys($pluginOptions)) . "</div>";
        
        // Check for required cashonrails settings
        $hasSecretKey = !empty($pluginOptions['cashonrailsSecretKey']);
        echo "<div class='" . ($hasSecretKey ? 'success' : 'error') . "'>cashonrails Secret Key: " . ($hasSecretKey ? 'CONFIGURED' : 'NOT CONFIGURED') . "</div>";
    } else {
        echo "<div class='error'>✗ Plugin options not loaded</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error loading SDK: " . $e->getMessage() . "</div>";
}
echo "</div>";

// 4. Test payment initialization directly
echo "<div class='section info'>";
echo "<h2>4. Direct Payment Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_payment'])) {
    echo "<h3>Testing Payment Initialization...</h3>";
    
    // Log the POST data
    echo "<div class='info'>";
    echo "<h4>Received POST Data:</h4>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    echo "</div>";
    
    // Test the payment function directly
    try {
        $testData = [
            'clientId' => (int)$_POST['clientId'],
            'invoiceIds' => array_map('intval', explode(',', trim($_POST['invoiceIds']))),
            'amount' => (float)$_POST['amount'],
            'email' => filter_var($_POST['email'], FILTER_VALIDATE_EMAIL),
            'currency' => 'NGN'
        ];
        
        echo "<div class='info'>";
        echo "<h4>Processed Test Data:</h4>";
        echo "<pre>" . print_r($testData, true) . "</pre>";
        echo "</div>";
        
        // Validation
        $errors = [];
        if ($testData['clientId'] <= 0) $errors[] = "Invalid client ID";
        if (empty($testData['invoiceIds'])) $errors[] = "No invoice IDs";
        if ($testData['amount'] <= 0) $errors[] = "Invalid amount";
        if (!$testData['email']) $errors[] = "Invalid email";
        
        if (!empty($errors)) {
            echo "<div class='error'>";
            echo "<h4>Validation Errors:</h4>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='success'>✓ All validation passed</div>";
            
            // Try to call the payment function
            if (function_exists('initializecashonrailsPayment') && !empty($pluginOptions)) {
                try {
                    $paymentUrl = initializecashonrailsPayment(
                        $pluginOptions,
                        $testData['clientId'],
                        $testData['invoiceIds'],
                        $testData['amount'],
                        $testData['email'],
                        $testData['currency']
                    );
                    
                    echo "<div class='success'>";
                    echo "<h4>✓ Payment URL Generated Successfully!</h4>";
                    echo "<p><strong>Payment URL:</strong> <a href='$paymentUrl' target='_blank'>$paymentUrl</a></p>";
                    echo "<p><a href='$paymentUrl' target='_blank' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Payment →</a></p>";
                    echo "</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>";
                    echo "<h4>✗ Payment Initialization Failed:</h4>";
                    echo "<p>" . $e->getMessage() . "</p>";
                    echo "</div>";
                }
            } else {
                echo "<div class='error'>Payment function not available or plugin options missing</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h4>Exception occurred:</h4>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Payment test form
echo "<div class='test-form'>";
echo "<h3>Test Payment Initialization</h3>";
echo "<form method='POST'>";
echo "<input type='hidden' name='test_payment' value='1'>";
echo "<div><label>Client ID:</label><input type='number' name='clientId' value='1' required></div><br>";
echo "<div><label>Invoice IDs (comma-separated):</label><input type='text' name='invoiceIds' value='123,124' required></div><br>";
echo "<div><label>Amount:</label><input type='number' name='amount' value='1000.50' step='0.01' required></div><br>";
echo "<div><label>Email:</label><input type='email' name='email' value='test@example.com' required></div><br>";
echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test Payment</button>";
echo "</form>";
echo "</div>";
echo "</div>";

// 5. Check recent logs
echo "<div class='section info'>";
echo "<h2>5. Recent Log Activity</h2>";
$logDir = __DIR__ . '/data';
if (is_dir($logDir)) {
    $logFiles = glob($logDir . '/*.log');
    if (!empty($logFiles)) {
        foreach ($logFiles as $logFile) {
            $filename = basename($logFile);
            $size = filesize($logFile);
            $modified = date('Y-m-d H:i:s', filemtime($logFile));
            echo "<div>$filename - Size: {$size}bytes - Modified: $modified</div>";
            
            // Show last few lines of each log
            if ($size > 0 && $size < 10000) { // Only show if file is not too large
                $content = file_get_contents($logFile);
                $lines = array_filter(explode("\n", $content));
                $lastLines = array_slice($lines, -5);
                if (!empty($lastLines)) {
                    echo "<div style='margin-left: 20px; font-size: 12px;'>";
                    echo "<strong>Last 5 lines:</strong>";
                    echo "<pre>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
                    echo "</div>";
                }
            }
        }
    } else {
        echo "<div class='warning'>No log files found</div>";
    }
} else {
    echo "<div class='error'>Data directory not found</div>";
}
echo "</div>";

// 6. Test form submission simulation
echo "<div class='section info'>";
echo "<h2>6. Form Submission Test</h2>";
echo "<div class='test-form'>";
echo "<h3>Test Form → initialize-payment.php</h3>";
echo "<form method='POST' action='initialize-payment.php' target='_blank'>";
echo "<div><label>Client ID:</label><input type='number' name='clientId' value='1' required></div><br>";
echo "<div><label>Invoice IDs:</label><input type='text' name='invoiceIds' value='123' required></div><br>";
echo "<div><label>Amount:</label><input type='number' name='amount' value='1000' step='0.01' required></div><br>";
echo "<div><label>Email:</label><input type='email' name='email' value='test@example.com' required></div><br>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Submit to initialize-payment.php</button>";
echo "</form>";
echo "</div>";
echo "</div>";

echo "<div class='section warning'>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Run the direct payment test above to see if the payment function works</li>";
echo "<li>Check if plugin options are properly configured in UISP</li>";
echo "<li>Test the form submission to initialize-payment.php</li>";
echo "<li>Check the log files for detailed error messages</li>";
echo "</ol>";
echo "</div>";
?>
