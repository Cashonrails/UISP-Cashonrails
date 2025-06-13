<?php
require_once __DIR__ . '/public.php';

// Debug: Log all incoming data
file_put_contents(__DIR__ . '/data/input_debug.log',
    date('[Y-m-d H:i:s] ') . "=== NEW REQUEST ===\n" .
    "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n" .
    "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n" .
    "GET data: " . print_r($_GET, true) . "\n" .
    "POST data: " . print_r($_POST, true) . "\n" .
    "RAW INPUT: " . file_get_contents('php://input') . "\n" .
    "HEADERS: " . print_r(getallheaders(), true) . "\n" .
    "========================\n",
    FILE_APPEND);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method Not Allowed']));
}

// Handle raw JSON or form-encoded data
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$data = is_array($jsonInput) ? $jsonInput : $_POST;

// Validate required fields
$required = ['clientId', 'invoiceIds', 'amount', 'email'];
$missingFields = [];

foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    $error = "Missing required fields: " . implode(', ', $missingFields);
    file_put_contents(__DIR__ . '/data/validation_error.log',
        date('[Y-m-d H:i:s] ') . $error . "\n" .
        "Available keys: " . implode(', ', array_keys($data)) . "\n" .
        "Input data: " . print_r($data, true) . "\n",
        FILE_APPEND);
    http_response_code(400);
    die(json_encode(['error' => $error]));
}

try {
    // Sanitize inputs
    $clientId = filter_var($data['clientId'], FILTER_VALIDATE_INT);
    $invoiceIdsString = trim($data['invoiceIds']);
    $amount = filter_var($data['amount'], FILTER_VALIDATE_FLOAT);
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);

    // Validate individual fields
    if ($clientId === false || $clientId <= 0) {
        throw new Exception("Invalid client ID: must be a positive integer");
    }

    if (empty($invoiceIdsString)) {
        throw new Exception("Invoice IDs cannot be empty");
    }

    $invoiceIds = array_filter(array_map('trim', explode(',', $invoiceIdsString)));
    $invoiceIds = array_map('intval', $invoiceIds);
    $invoiceIds = array_filter($invoiceIds, function ($id) {
        return $id > 0;
    });

    if (empty($invoiceIds)) {
        throw new Exception("No valid invoice IDs provided");
    }

    if ($amount === false || $amount <= 0) {
        throw new Exception("Invalid amount: must be greater than 0");
    }

    if (!$email) {
        throw new Exception("Invalid email address");
    }

    $paymentData = [
        'clientId' => $clientId,
        'invoiceIds' => $invoiceIds,
        'amount' => round($amount, 2),
        'email' => $email,
        'currency' => 'NGN'
    ];

    // Log sanitized input
    file_put_contents(__DIR__ . '/data/payment_data.log',
        date('[Y-m-d H:i:s] ') . "Processed payment data:\n" . print_r($paymentData, true) . "\n",
        FILE_APPEND);

    // Validate plugin options
    if (empty($pluginOptions)) {
        throw new Exception("Plugin options not loaded. Check plugin configuration.");
    }

    if (empty($pluginOptions['cashonrailsSecretKey'])) {
        throw new Exception("cashonrails secret key not configured. Please check plugin settings.");
    }

    // Initialize payment
    $redirectUrl = initializecashonrailsPayment(
        $pluginOptions,
        $paymentData['clientId'],
        $paymentData['invoiceIds'],
        $paymentData['amount'],
        $paymentData['email'],
        $paymentData['currency']
    );

    // Log successful payment URL
    file_put_contents(__DIR__ . '/data/payment_data.log',
        date('[Y-m-d H:i:s] ') . "Payment URL generated: " . $redirectUrl . "\n",
        FILE_APPEND);

    // Redirect user to payment gateway
    header('Location: ' . $redirectUrl);
    exit;

} catch (Exception $e) {
    $errorMessage = "Payment Error: " . $e->getMessage();
    file_put_contents(__DIR__ . '/data/payment_error.log',
        date('[Y-m-d H:i:s] ') . $errorMessage . "\n" .
        "Input Data: " . print_r($data, true) . "\n" .
        "Stack trace: " . $e->getTraceAsString() . "\n",
        FILE_APPEND);

    http_response_code(500);

    if (!empty($pluginOptions['testMode'])) {
        die(json_encode([
            'error' => $errorMessage,
            'debug' => [
                'data' => $data,
                'plugin_options_loaded' => !empty($pluginOptions),
                'has_secret_key' => !empty($pluginOptions['cashonrailsSecretKey'] ?? '')
            ]
        ]));
    } else {
        die(json_encode(['error' => 'Payment initialization failed. Please try again.']));
    }
}
