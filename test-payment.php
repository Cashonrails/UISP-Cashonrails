<?php
/**
 * Test payment page for debugging
 * Access this page directly to test payment functionality
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test cashonrails Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-form {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .btn-test {
            background-color: #28a745;
        }
        .btn-test:hover {
            background-color: #1e7e34;
        }
        .debug-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>cashonrails Payment Gateway Test</h1>
        
        <!-- Quick Test Form -->
        <div class="test-form">
            <h3>Quick Payment Test</h3>
            <p>Use this form to test the payment functionality with sample data:</p>
            
            <form method="GET" action="payment-form.php">
                <div class="form-group">
                    <label for="test_client_id">Client ID:</label>
                    <input type="number" id="test_client_id" name="client_id" value="1" required>
                </div>
                
                <div class="form-group">
                    <label for="test_invoice_id">Invoice ID:</label>
                    <input type="text" id="test_invoice_id" name="invoice_id" value="123" required>
                </div>
                
                <div class="form-group">
                    <label for="test_amount">Amount (NGN):</label>
                    <input type="number" id="test_amount" name="amount" value="1000" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="test_email">Email (optional - can be filled in next step):</label>
                    <input type="email" id="test_email" name="email" placeholder="test@example.com">
                </div>
                
                <button type="submit" class="btn-test">Test Payment Form</button>
            </form>
        </div>
        
        <!-- Direct Payment Test -->
        <div class="test-form">
            <h3>Direct Payment Initialization Test</h3>
            <p>Test payment initialization directly:</p>
            
            <form method="POST" action="initialize-payment.php">
                <input type="hidden" name="action" value="pay">
                
                <div class="form-group">
                    <label for="direct_client_id">Client ID:</label>
                    <input type="number" id="direct_client_id" name="clientId" value="1" required>
                </div>
                
                <div class="form-group">
                    <label for="direct_invoice_ids">Invoice IDs (comma-separated):</label>
                    <input type="text" id="direct_invoice_ids" name="invoiceIds" value="123,124" required>
                </div>
                
                <div class="form-group">
                    <label for="direct_amount">Amount (NGN):</label>
                    <input type="number" id="direct_amount" name="amount" value="1500.50" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="direct_email">Email:</label>
                    <input type="email" id="direct_email" name="email" value="test@example.com" required>
                </div>
                
                <button type="submit" class="btn-test">Initialize Payment Directly</button>
            </form>
        </div>
        
        <!-- Configuration Check -->
        <div class="debug-section">
            <h3>System Status Check</h3>
            
            <?php
            $checks = [];
            
            // Check if required files exist
            $requiredFiles = [
                'public.php' => 'Main public entry point',
                'main.php' => 'Core payment logic',
                'initialize-payment.php' => 'Payment initialization script',
                'data/' => 'Data directory for logs'
            ];
            
            foreach ($requiredFiles as $file => $description) {
                $path = __DIR__ . '/' . $file;
                $exists = file_exists($path);
                $checks[] = [
                    'item' => "$description ($file)",
                    'status' => $exists ? 'OK' : 'MISSING',
                    'success' => $exists
                ];
            }
            
            // Check if data directory is writable
            $dataDir = __DIR__ . '/data';
            $writable = is_dir($dataDir) && is_writable($dataDir);
            $checks[] = [
                'item' => 'Data directory writable',
                'status' => $writable ? 'OK' : 'NOT WRITABLE',
                'success' => $writable
            ];
            
            // Check for recent logs
            $logFiles = ['input_debug.log', 'payment_data.log', 'payment_error.log'];
            foreach ($logFiles as $logFile) {
                $logPath = $dataDir . '/' . $logFile;
                $hasRecentLogs = file_exists($logPath) && (time() - filemtime($logPath)) < 300; // 5 minutes
                $checks[] = [
                    'item' => "Recent activity in $logFile",
                    'status' => $hasRecentLogs ? 'RECENT ACTIVITY' : 'NO RECENT ACTIVITY',
                    'success' => true // This is informational
                ];
            }
            
            foreach ($checks as $check):
            ?>
            <div class="status <?php echo $check['success'] ? 'success' : 'error'; ?>">
                <strong><?php echo htmlspecialchars($check['item']); ?>:</strong> 
                <?php echo htmlspecialchars($check['status']); ?>
            </div>
            <?php endforeach; ?>
            
            <h4>Latest Error Logs (if any):</h4>
            <?php
            $errorLogPath = $dataDir . '/payment_error.log';
            if (file_exists($errorLogPath)) {
                $errorContent = file_get_contents($errorLogPath);
                $lastErrors = array_slice(explode("\n", $errorContent), -10);
                echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">';
                echo htmlspecialchars(implode("\n", array_filter($lastErrors)));
                echo '</pre>';
            } else {
                echo '<p><em>No error log found (this is good!)</em></p>';
            }
            ?>
            
            <h4>Server Information:</h4>
            <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">
PHP Version: <?php echo PHP_VERSION; ?>
Server Software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?>
Current Directory: <?php echo __DIR__; ?>
Current Time: <?php echo date('Y-m-d H:i:s'); ?>
            </pre>
        </div>
    </div>
</body>
</html>
