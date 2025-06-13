<?php
// Enhanced parameter validation with fallbacks
$clientId = 0;
$invoiceIds = ''; // Change to plural for multiple support
$amount = 0.00;
$email = '';

// Get parameters from multiple sources
if (isset($_GET['client_id'])) {
    $clientId = (int)$_GET['client_id'];
} elseif (isset($_GET['clientId'])) {
    $clientId = (int)$_GET['clientId'];
}

if (isset($_GET['invoice_id'])) {
    $invoiceIds = (string)$_GET['invoice_id'];
} elseif (isset($_GET['invoiceId'])) {
    $invoiceIds = (string)$_GET['invoiceId'];
} elseif (isset($_GET['invoice_ids'])) {
    $invoiceIds = (string)$_GET['invoice_ids']; // comma-separated expected
}

if (isset($_GET['amount'])) {
    $amount = round((float)$_GET['amount'], 2);
}

if (isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
}

// Log debug info
file_put_contents(__DIR__ . '/data/form_debug.log',
    date('[Y-m-d H:i:s] ') . "Payment form loaded with parameters:\n" .
    "GET: " . print_r($_GET, true) . "\n" .
    "Parsed - ClientID: $clientId, InvoiceIDs: $invoiceIds, Amount: $amount, Email: $email\n",
    FILE_APPEND);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashonrails Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"],
        input[type="number"],
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[readonly] {
            background-color: #f9f9f9;
            color: #666;
        }
        button {
            background-color: #00C851;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background-color: #007E33;
        }
        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
        }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            margin-top: 20px;
            font-size: 12px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Complete Your Payment</h2>

    <?php if ($clientId <= 0 || empty($invoiceIds) || $amount <= 0): ?>
        <div class="error">
            <strong>Error:</strong> Missing required payment information. Please ensure you accessed this page through a valid payment link.
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                Client ID: <?php echo (int)$clientId; ?><br>
                Invoice ID(s): <?php echo htmlspecialchars($invoiceIds); ?><br>
                Amount: <?php echo number_format($amount, 2, '.', ''); ?><br>
                URL Parameters: <?php echo htmlspecialchars(http_build_query($_GET)); ?>
            </div>
        </div>
    <?php endif; ?>

    <form id="paymentForm" method="POST" action="initialize-payment.php">
        <input type="hidden" name="action" value="pay">

        <div class="form-group">
            <label for="clientId">Client ID:</label>
            <input type="number"
                   id="clientId"
                   name="clientId"
                   value="<?php echo (int)$clientId; ?>"
                   required readonly>
        </div>

        <div class="form-group">
            <label for="invoiceIds">Invoice ID(s):</label>
            <input type="text"
                   id="invoiceIds"
                   name="invoiceIds"
                   value="<?php echo htmlspecialchars($invoiceIds); ?>"
                   required readonly>
            <small>Multiple invoice IDs should be comma-separated</small>
        </div>

        <div class="form-group">
            <label for="amount">Amount (NGN):</label>
            <input type="number"
                   id="amount"
                   name="amount"
                   value="<?php echo number_format($amount, 2, '.', ''); ?>"
                   step="0.01"
                   min="0.01"
                   required readonly>
        </div>

        <div class="form-group">
            <label for="email">Email Address:</label>
            <input type="email"
                   id="email"
                   name="email"
                   value="<?php echo htmlspecialchars($email); ?>"
                   required
                   placeholder="Enter your email address">
            <div id="emailError" class="error" style="display: none;"></div>
        </div>

        <button type="submit" id="submitBtn">Proceed to Cashonrails Payment</button>
    </form>

    <div class="debug-info">
        <strong>Debug Information:</strong><br>
        Form Action: initialize-payment.php<br>
        Current Time: <?php echo date('Y-m-d H:i:s'); ?><br>
        Server: <?php echo $_SERVER['HTTP_HOST'] ?? 'Unknown'; ?><br>
        PHP Version: <?php echo PHP_VERSION; ?>
    </div>
</div>

<script>
    document.getElementById('paymentForm').addEventListener('submit', function (e) {
        const submitBtn = document.getElementById('submitBtn');
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('emailError');
        const clientId = document.getElementById('clientId').value;
        const invoiceIds = document.getElementById('invoiceIds').value;
        const amount = document.getElementById('amount').value;

        // Reset error
        emailError.style.display = 'none';
        emailError.textContent = '';

        const errors = [];

        if (!clientId || parseInt(clientId) <= 0) {
            errors.push('Valid Client ID is required');
        }
        if (!invoiceIds.trim()) {
            errors.push('Invoice ID(s) is required');
        }
        if (!amount || parseFloat(amount) <= 0) {
            errors.push('Valid amount is required');
        }
        if (!emailInput.value || !emailInput.value.includes('@')) {
            errors.push('Valid email address is required');
        }

        if (errors.length > 0) {
            emailError.textContent = errors.join(', ');
            emailError.style.display = 'block';
            e.preventDefault();
            return false;
        }

        // Show loading state
        submitBtn.textContent = 'Processing...';
        submitBtn.disabled = true;

        // Reset after 10s fallback
        setTimeout(() => {
            submitBtn.textContent = 'Proceed to Cashonrails Payment';
            submitBtn.disabled = false;
        }, 10000);

        return true;
    });

    window.addEventListener('load', function () {
        const emailInput = document.getElementById('email');
        if (!emailInput.value) {
            emailInput.focus();
        }
    });
</script>
</body>
</html>
