<?php
declare(strict_types=1);

use Ubnt\UcrmPluginSdk\Service\UcrmSecurity;
use Ubnt\UcrmPluginSdk\Service\UcrmApi;

require_once __DIR__ . '/vendor/autoload.php';

$configPath = __DIR__ . '/data/config.json';

if (!file_exists($configPath)) {
    die("Plugin configuration file not found.");
}

$config = json_decode(file_get_contents($configPath), true);

$publicKey = $config['cashonrailsPublicKey'] ?? null;
$secretKey = $config['cashonrailsSecretKey'] ?? null;
$secretKey = $config['currency'] ?? 'NGN';
$webhookSecret = $config['webhookSecret'] ?? null;
$testMode = $config['testMode'] ?? false;
$paymentDescription = $config['paymentDescription'] ?? 'Pay your invoice using CashOnRails payment gateway';

if (!$publicKey || !$secretKey) {
    die("Please configure your CashOnRails Public and Secret Keys in the plugin settings.");
}

function processRequest(): void
{
    global $secretKey;

    $action = $_GET['action'] ?? null;
    $token = $_GET['_token'] ?? null;

    if (!$action) {
        http_response_code(400);
        exit("Missing 'action' parameter.");
    }

    switch ($action) {
        case 'pay':
            $security = UcrmSecurity::create();
            if (!$security) {
                http_response_code(403);
                exit("Security check failed.");
            }

            $user = $security->getUser();
            if (!$user || !$user->clientId) {
                http_response_code(403);
                exit("Unable to retrieve client information.");
            }

            $clientId = $user->clientId;
            $api = UcrmApi::create();

            if (!$token) {
                http_response_code(400);
                exit("Missing payment token.");
            }

            $get_invoice = $api->get('payment-tokens/' . $token);
            $invoices = $api->get('invoices', [
                'clientId' => $clientId,
                'order' => 'createdDate',
                'direction' => 'DESC',
            ]);

            if (empty($invoices)) {
                http_response_code(400);
                exit("No unpaid invoices found.");
            }

            if (empty($get_invoice['invoiceId'])) {
                $invoiceIds = [];
                $totalAmount = 0;
                foreach ($invoices as $invoice) {
                    $invoiceIds[] = $invoice['id'];
                    $totalAmount += $invoice['amountToPay'];
                }
            } else {
                $invoiceIds = [$get_invoice['invoiceId']];
                $totalAmount = $get_invoice['amount'] ?? 0;
            }

            $client = $api->get("clients/{$clientId}");
            $email = $client['username'] ?? '';

            if (!$email) {
                http_response_code(400);
                exit("Client email not found.");
            }

            try {
                $currency = 'NGN';

                if (empty($invoiceIds) || $totalAmount <= 0) {
                    throw new Exception("No valid invoices or amount to process.");
                }

                // Create customer
                $customerPayload = json_encode([
                    'email' => $email,
                    'first_name' => 'NetcomSS',
                    'last_name' => 'Customer_' . $clientId,
                    'phone' => '08000000000',
                ]);

                $ch = curl_init('https://mainapi.cashonrails.com/api/v1/customer');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $customerPayload,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $secretKey,
                    ],
                ]);

                $customerResponse = curl_exec($ch);
                curl_close($ch);
                $customerData = json_decode($customerResponse, true);

                if (empty($customerData['data']['customer_code'])) {
                    throw new Exception("Customer creation failed.");
                }

                $reference = $token;
                $host = $_SERVER['HTTP_HOST'];
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $redirectUrl = "{$scheme}://{$host}/crm/_plugins/cashonrails-payment-gateway/public.php?action=verify";

                $paymentPayload = json_encode([
                    'client_id'     => $clientId,
                    'customer_code' => $customerData['data']['customer_code'],
                    'reference'     => $reference,
                    'amount'        => (string) $totalAmount,
                    'currency'      => $currency,
                    'email'         => $email,
                    'redirectUrl'   => $redirectUrl,
                ]);

                $ch = curl_init('https://mainapi.cashonrails.com/api/v1/transaction/initialize');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $paymentPayload,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $secretKey,
                    ],
                ]);

                $paymentResponse = curl_exec($ch);
                curl_close($ch);
                $paymentData = json_decode($paymentResponse, true);

//                var_dump($paymentData);
                if (!empty($paymentData['data']['authorization_url'])) {
                    echo "<script>window.location = '" . $paymentData['data']['authorization_url'] . "';</script>";
                    exit;
                }
                if ($paymentData['success'] === false) {
                    $message = htmlspecialchars($paymentData['message'], ENT_QUOTES, 'UTF-8');
                    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Checkout Failed',
        text: "{$message}",
        confirmButtonText: 'OK'
    }).then(() => {
        window.location.href = window.location.origin + '/crm/client-zone';
    });
</script>
</body>
</html>
HTML;
                    exit;
                }


//                throw new Exception("Payment initialization failed.");
            } catch (Exception $e) {
                http_response_code(400);
                $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Checkout Failed',
        text: "{$message}",
        confirmButtonText: 'OK'
    }).then(() => {
        window.location.href = window.location.origin + '/crm/client-zone';
    });
</script>
</body>
</html>
HTML;
                exit;
            }

            break;

        case 'verify':
            $reference = $_GET['reference'] ?? null;
            if (!$reference) {
                http_response_code(400);
                exit('Missing payment reference.');
            }

            $api = UcrmApi::create();
            $security = UcrmSecurity::create();
            $user = $security->getUser();

            if (!$user || !$user->clientId) {
                http_response_code(403);
                exit("Unauthorized access.");
            }

            $clientId = $user->clientId;

            $verifyUrl = "https://mainapi.cashonrails.com/api/v1/s2s/transaction/verify/{$reference}";

            $ch = curl_init($verifyUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$secretKey}",
                ],
            ]);

            $response = curl_exec($ch);
            curl_close($ch);
            $verifyData = json_decode($response, true);

            if (empty($verifyData['success']) || $verifyData['success'] !== true) {
                http_response_code(400);
                exit('Payment verification failed or payment not successful.');
            }

            $get_invoice = $api->get("payment-tokens/{$reference}");
            $invoiceIdsToPay = [];

            if (empty($get_invoice['invoiceId'])) {
                $invoices = $api->get('invoices', [
                    'clientId' => $clientId,
                    'order' => 'createdDate',
                    'direction' => 'DESC'
                ]);
                foreach ($invoices as $invoice) {
                    $invoiceIdsToPay[] = $invoice['id'];
                }
            } else {
                $invoiceIdsToPay = [$get_invoice['invoiceId']];
            }

            foreach ($invoiceIdsToPay as $invoiceId) {
                try {
                    $api->post("payments", [
                        "clientId"     => $clientId,
                        "methodId"     => '9bb15b8e-7d88-4f53-8e2d-17a7a54f80bf',
                        "createdDate"  => date('c'),
                        "amount"       => (float) $verifyData['data']['amount'],
                        "currencyCode" => $verifyData['data']['currency'],
                        "userId"       => 1003,
                        "attributes"   => new stdClass(),
                        "invoiceIds"   => [$invoiceId],
                    ]);

                    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Successful</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Payment Successful',
        text: 'Invoice #{$invoiceId} has been marked as paid.',
        confirmButtonText: 'OK'
    }).then(() => {
        window.location.href = window.location.origin + '/crm/client-zone/account-statement';
    });
</script>
</body>
</html>
HTML;
                } catch (Exception $e) {
//                    http_response_code(500);
//                    echo "<h2>❌ Error updating invoice</h2>";
//                    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
            break;

        default:
            echo "✅ UCRM CashOnRails plugin is working.";
            break;
    }
}

processRequest();
