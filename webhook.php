<?php

/**
 * Webhook handler for cashonrails payments
 * 
 * This script handles webhook notifications from cashonrails.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Ubnt\UcrmPluginSdk\Service\UcrmApi;
use Ubnt\UcrmPluginSdk\Service\UcrmOptionsManager;

// Load plugin configuration
$optionsManager = UcrmOptionsManager::create();
$options = $optionsManager->loadOptions();

// Get the webhook secret from options
$webhookSecret = $options['webhookSecret'] ?? '';

// Verify webhook signature if secret is configured
if (!empty($webhookSecret)) {
    $signature = $_SERVER['HTTP_X_cashonrails_SIGNATURE'] ?? '';
    $payload = file_get_contents('php://input');
    
    if (!$signature || hash_hmac('sha512', $payload, $webhookSecret) !== $signature) {
        header('HTTP/1.1 401 Unauthorized');
        exit('Invalid signature');
    }
}

// Get request body
$payload = json_decode(file_get_contents('php://input'), true);

// Verify the webhook is valid
if (!$payload || !isset($payload['event'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid webhook payload');
}

// Log webhook payload for debugging
file_put_contents(
    __DIR__ . '/data/webhook.log',
    date('Y-m-d H:i:s') . ' - ' . json_encode($payload) . PHP_EOL,
    FILE_APPEND
);

// Process different event types
switch ($payload['event']) {
    case 'charge.success':
        handleSuccessfulPayment($payload['data'], $options);
        break;
        
    case 'transfer.success':
        // Handle transfer success if needed
        break;
        
    // Add more event handlers as needed
    
    default:
        // Ignore other events
        break;
}

// Return 200 to acknowledge receipt
http_response_code(200);
echo 'Webhook processed';
exit;

/**
 * Handle successful payment webhook
 */
function handleSuccessfulPayment($data, $options)
{
    // Check if payment reference exists and follows our format
    $reference = $data['reference'] ?? '';
    if (!preg_match('/^ucrm_\d+_(\d+)$/', $reference, $matches)) {
        return; // Not our payment or invalid format
    }
    
    $invoiceId = (int) $matches[1];
    
    // Extract payment details
    $amount = ($data['amount'] ?? 0) / 100; // Convert from kobo to base currency
    $currency = $data['currency'] ?? '';
    $customerEmail = $data['customer']['email'] ?? '';
    $paymentDate = new DateTime($data['paid_at'] ?? 'now');
    
    // Find client by email
    $api = UcrmApi::create();
    
    try {
        $clients = $api->get('clients', [
            'email' => $customerEmail,
        ]);
        
        if (empty($clients)) {
            logError("Client not found for email: $customerEmail");
            return;
        }
        
        $clientId = $clients[0]['id'];
        
        // Check if the invoice exists and belongs to this client
        $invoice = $api->get("invoices/$invoiceId");
        if ($invoice['clientId'] != $clientId) {
            logError("Invoice $invoiceId does not belong to client $clientId");
            return;
        }
        
        // Check if payment with this reference already exists to avoid duplicates
        $existingPayments = $api->get('payments', [
            'providerPaymentId' => $reference,
        ]);
        
        if (!empty($existingPayments)) {
            logError("Payment with reference $reference already exists");
            return;
        }
        
        // Create payment in UCRM
        $paymentData = [
            'clientId' => $clientId,
            'method' => 'cashonrails',
            'amount' => $amount,
            'currencyCode' => $currency,
            'note' => 'cashonrails webhook payment. Reference: ' . $reference,
            'invoiceIds' => [$invoiceId],
            'providerName' => 'cashonrails',
            'providerPaymentId' => $reference,
            'providerPaymentTime' => $paymentDate->format('Y-m-d\TH:i:sP'),
            'applyToInvoicesAutomatically' => true,
        ];
        
        $payment = $api->post('payments', $paymentData);
        
        // Log success
        file_put_contents(
            __DIR__ . '/data/payments.log',
            date('Y-m-d H:i:s') . " - Payment created: {$payment['id']} for invoice {$invoiceId}, amount: {$amount} {$currency}\n",
            FILE_APPEND
        );
        
    } catch (Exception $e) {
        logError('Error processing webhook payment: ' . $e->getMessage());
    }
}

/**
 * Log error message
 */
function logError($message)
{
    file_put_contents(
        __DIR__ . '/data/errors.log',
        date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL,
        FILE_APPEND
    );
}
