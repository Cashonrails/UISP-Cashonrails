<?php
declare(strict_types=1);

use Ubnt\UcrmPluginSdk\Service\UcrmApi;

require_once __DIR__ . '/vendor/autoload.php';

// Replace with your CashOnRails secret key
$secretKey = 'sk_test_ymkikuizpglsxhrzal6rcxkvxbk1uvedxqlyjp0';

// Get transaction reference from redirect URL
$reference = $_GET['reference'] ?? null;

if (!$reference) {
    http_response_code(400);
    die('Missing payment reference.');
}

// Call CashOnRails to verify the transaction
$verifyUrl = "https://mainapi.cashonrails.com/api/v1/transaction/verify/{$reference}";

$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$secretKey}",
]);

$response = curl_exec($ch);
curl_close($ch);

$verifyData = json_decode($response, true);

// Optional: log or display the response for debugging
// echo '<pre>'; print_r($verifyData); echo '</pre>'; exit;

// Check if the transaction is successful
if (!isset($verifyData['data']['status']) || $verifyData['data']['status'] !== 'success') {
    http_response_code(400);
    die('Payment verification failed or payment not successful.');
}

// Extract invoice ID from reference
$parts = explode('_', $reference);
$invoiceId = $parts[1] ?? null;

if (!$invoiceId || !is_numeric($invoiceId)) {
    http_response_code(400);
    die('Invalid invoice ID.');
}

// Approve invoice using UCRM API
try {
    $api = UcrmApi::create();
    $api->patch("invoices/{$invoiceId}", [
        'status' => 'approved',
    ]);

    echo "<h2>✅ Payment successful!</h2>";
    echo "<p>Invoice #{$invoiceId} has been approved.</p>";
} catch (Exception $e) {
    http_response_code(500);
    echo "<h2>❌ Error updating invoice</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
