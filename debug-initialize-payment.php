<?php
/**
 * Debug version of initialize-payment.php
 * This will show us exactly what's happening step by step
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create data directory if needed
$dataDir = __DIR__ . '/data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Function to log and display debug info
function debugLog($message, $data = null) {
    global $dataDir;
    $timestamp = date('[Y-m-d H:i:s] ');
    $logMessage = $timestamp . $message;
    if ($data !== null) {
        $logMessage .= "\nData: " . print_r($data, true);
    }
    $logMessage .= "\n";
    
    // Log to file
    file_put_contents($dataDir . '/debug_initialize.log', $logMessage, FILE_APPEND);
    
    // Also display on screen
    echo "<div style='background: #f8f9fa; padding: 10px; margin: 5px 0; border-left: 4px solid #007bff;'>";
    echo "<strong>$timestamp</strong> $message";
    if ($data !== null) {
        echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
    }
    echo "</div>";
}

// Start debugging
echo "<h1>Debug Initialize Payment</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;}</style>";

debugLog("=== DEBUGGING INITIALIZE-PAYMENT.PHP ===");
debugLog("REQUEST_METHOD", $_SERVER['REQUEST_METHOD']);
debugLog("All SERVER vars", $_SERVER);
debugLog("GET data", $_GET);
debugLog("POST data", $_POST);
debugLog("Raw input", file_get_contents('php://input'));

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Not a POST request");
    http_response_code(405);
    die(json_encode(['error' => 'Method Not Allowed']));
}

debugLog("✓ POST request confirmed");

// Check each required field individually
$required = ['clientId', 'invoiceIds', 'amount', 'email'];
$missingFields = [];
$availableFields = [];

debugLog("Checking required fields", $required);

foreach ($required as $field) {
    if (isset($_POST[$field])) {
        $availableFields[$field] = $_POST[$field];
        debugLog("✓ Field '$field' is present", $_POST[$field]);
        
        // Check if it's empty
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
            debugLog("✗ Field '$field' is empty");
        } else {
            debugLog("✓ Field '$field' has value: " . $_POST[$field]);
        }
    } else {
        $missingFields[] = $field;
        debugLog("✗ Field '$field' is missing completely");
    }
}

debugLog("Available fields", $availableFields);
debugLog("Missing fields", $missingFields);

if (!empty($missingFields)) {
    $error = "Missing required fields: " . implode(', ', $missingFields);
    debugLog("VALIDATION ERROR", $error);
    http_response_code(400);
    die(json_encode(['error' => $error]));
}

debugLog("✓ All required fields are present and not empty");

try {
    // Process and sanitize inputs
    debugLog("Processing inputs...");
    
    $clientId = filter_var($_POST['clientId'], FILTER_VALIDATE_INT);
    debugLog("clientId after filtering", $clientId);
    
    $invoiceIdsString = trim($_POST['invoiceIds']);
    debugLog("invoiceIds string", $invoiceIdsString);
    
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    debugLog("amount after filtering", $amount);
    
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    debugLog("email after filtering", $email);
    
    // Validate processed data
    if ($clientId === false || $clientId <= 0) {
        throw new Exception("Invalid client ID: " . $_POST['clientId']);
    }
    
    if (empty($invoiceIdsString)) {
        throw new Exception("Invoice IDs cannot be empty");
    }
    
    $invoiceIds = array_filter(array_map('trim', explode(',', $invoiceIdsString)));
    $invoiceIds = array_map('intval', $invoiceIds);
    $invoiceIds = array_filter($invoiceIds, function($id) { return $id > 0; });
    
    debugLog("Processed invoice IDs", $invoiceIds);
    
    if (empty($invoiceIds)) {
        throw new Exception("No valid invoice IDs provided");
    }
    
    if ($amount === false || $amount <= 0) {
        throw new Exception("Invalid amount: " . $_POST['amount']);
    }
    
    if (!$email) {
        throw new Exception("Invalid email address: " . $_POST['email']);
    }
    
    $paymentData = [
        'clientId' => $clientId,
        'invoiceIds' => $invoiceIds,
        'amount' => round($amount, 2),
        'email' => $email,
        'currency' => 'NGN'
    ];

    debugLog("✓ Payment data validated", $paymentData);

    // Try to load the public.php file to get plugin options
    debugLog("Loading public.php...");
    
    try {
        require_once __DIR__ . '/public.php';
        debugLog("✓ public.php loaded");
        
        global $pluginOptions;
        if (empty($pluginOptions)) {
            throw new Exception("Plugin options not loaded from public.php");
        }
        
        debugLog("✓ Plugin options available", array_keys($pluginOptions));
        
        if (empty($pluginOptions['cashonrailsSecretKey'])) {
            throw new Exception("cashonrails secret key not configured");
        }
        
        debugLog("✓ cashonrails secret key is configured");
        
    } catch (Exception $e) {
        debugLog("ERROR loading public.php", $e->getMessage());
        throw $e;
    }

    // Try to initialize payment
    debugLog("Attempting to initialize payment...");
    
    if (!function_exists('initializecashonrailsPayment')) {
        throw new Exception("initializecashonrailsPayment function not available");
    }

    $redirectUrl = initializecashonrailsPayment(
        $pluginOptions,
        $paymentData['clientId'],
        $paymentData['invoiceIds'],
        $paymentData['amount'],
        $paymentData['email'],
        $paymentData['currency']
    );

    debugLog("✓ Payment URL generated", $redirectUrl);

    // Instead of redirecting, show the URL for testing
    echo "<div style='background: #d4edda; padding: 20px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h2>✓ SUCCESS!</h2>";
    echo "<p><strong>Payment URL generated:</strong></p>";
    echo "<p><a href='$redirectUrl' target='_blank'>$redirectUrl</a></p>";
    echo "<p><a href='$redirectUrl' target='_blank' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to cashonrails →</a></p>";
    echo "</div>";

} catch (Exception $e) {
    debugLog("EXCEPTION CAUGHT", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    die(json_encode(['error' => $e->getMessage()]));
}

debugLog("=== END DEBUG SESSION ===");
?>
