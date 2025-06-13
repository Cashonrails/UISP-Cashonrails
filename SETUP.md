# cashonrails Payment Gateway for UCRM/UISP

This plugin integrates cashonrails payment gateway with UCRM/UISP to allow clients to pay their invoices online.

## Installation Guide

### Prerequisites
- UCRM/UISP version 2.10.0 or higher
- PHP 7.2 or higher with cURL extension enabled
- cashonrails merchant account

### Directory Structure
Your plugin directory should have the following structure:
```
cashonrails-plugin/
├── main.php
├── manifest.json
├── public.php (optional)
├── data/
│   └── plugin.json
```

### Step-by-Step Installation

1. **Create the plugin directory structure**

   Create a new directory for your plugin in the UCRM/UISP plugins directory, typically located at:
   ```
   /data/ucrm/data/plugins/cashonrails-plugin/
   ```

2. **Create and configure files**

   - Copy the `main.php` file into your plugin directory
   - Copy the `plugin.json` file into the `data` subdirectory
   - Ensure the `data` directory has write permissions:
     ```bash
     mkdir -p data
     chmod 755 data
     ```

3. **Configure the plugin.json file**

   Edit the `data/plugin.json` file to add your cashonrails credentials:
   - `cashonrailsPublicKey`: Your cashonrails public key
   - `cashonrailsSecretKey`: Your cashonrails secret key
   - `successUrl`: URL to redirect after successful payment (e.g., `https://your-ucrm.com/payment-success`)
   - `failureUrl`: URL to redirect after failed payment (e.g., `https://your-ucrm.com/payment-failed`)
   - `currencyCode`: Currency code for payments (e.g., NGN, USD, GHS)

4. **Create manifest.json**

   Create a `manifest.json` file in your plugin directory with the following content:
   ```json
   {
     "version": "1",
     "information": {
       "name": "cashonrails Payment Gateway",
       "displayName": "cashonrails Payment Gateway",
       "description": "Allows clients to pay invoices using cashonrails payment gateway",
       "url": "https://cashonrails.com/",
       "version": "1.0.0",
       "ucrmVersionCompliancy": {
         "min": "2.10.0",
         "max": null
       },
       "unmsVersionCompliancy": {
         "min": "1.0.0",
         "max": null
       },
       "author": "Your Name"
     },
     "menu": [
       {
         "key": "cashonrails",
         "label": "cashonrails Payment",
         "type": "client",
         "target": "iframe"
       }
     ]
   }
   ```

5. **Enable the plugin**

   - Log in to your UCRM/UISP admin panel
   - Go to System > Plugins
   - Find the cashonrails plugin and click Enable
   - Click on Settings and enter your cashonrails credentials

## Troubleshooting

### Common Issues

1. **PHP code is displayed instead of executed**
   - Ensure your server is properly configured to execute PHP files
   - Check that mod_php is enabled if using Apache
   - Verify that PHP is installed and functioning correctly

2. **Permission denied errors in logs**
   - Ensure the `data` directory is writable by the web server user:
     ```bash
     chown -R www-data:www-data data
     chmod 755 data
     ```

3. **"Plugin configuration not found" error**
   - Make sure `plugin.json` exists in the `data` directory
   - Check that the file is readable by the web server

4. **Callback URL issues**
   - Ensure your UCRM/UISP installation is accessible from the internet
   - Verify that your cashonrails account is properly configured with the correct callback URLs

### Debugging

To enable logging for troubleshooting:

1. Add these lines at the top of your `main.php` file (after the PHP opening tag):
   ```php
   // Enable error logging
   ini_set('display_errors', 1);
   ini_set('log_errors', 1);
   ini_set('error_log', __DIR__ . '/data/cashonrails_error.log');
   ```

2. Check the log file for errors:
   ```bash
   tail -f data/cashonrails_error.log
   ```

## Payment Flow

1. Client selects invoices to pay
2. Client clicks "Pay Now" button
3. cashonrails payment popup appears
4. Client enters payment details and completes payment
5. cashonrails redirects to the callback URL
6. Plugin verifies the transaction with cashonrails
7. Plugin creates a payment record in UCRM/UISP
8. Client is redirected to the success URL

## Support

If you need assistance with this plugin, please contact:
- Your Name
- your.email@example.com
