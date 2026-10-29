<?php
/**
 * CRM Integration Module - OAuth Callback Handler
 *
 * Handles OAuth authorization callbacks from CRM systems (AmoCRM, etc.)
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

// Initialize Netcat
$NETCAT_FOLDER = join(strstr(__FILE__, "/") ? "/" : "\\", array_slice(preg_split("/[\/\\\]+/", __FILE__), 0, -4)) . (strstr(__FILE__, "/") ? "/" : "\\");
require_once $NETCAT_FOLDER . 'vars.inc.php';
require_once $INCLUDE_FOLDER . 'index.php';

$nc_core = nc_core::get_object();

// Get parameters
$code = $nc_core->input->fetch_get('code');
$state = $nc_core->input->fetch_get('state');
$error = $nc_core->input->fetch_get('error');
$error_description = $nc_core->input->fetch_get('error_description');

// Parse state (format: site_id_provider_id)
$state_parts = explode('_', $state);
$site_id = isset($state_parts[0]) ? (int)$state_parts[0] : 0;
$provider_id = isset($state_parts[1]) ? (int)$state_parts[1] : 0;

// Prepare result
$success = false;
$message = '';

// Check for OAuth error
if ($error) {
    $message = $error_description ?: $error;
} elseif (!$code) {
    $message = 'Authorization code not received';
} elseif (!$site_id || !$provider_id) {
    $message = 'Invalid state parameter';
} else {
    try {
        // Create provider and exchange code for tokens
        $provider = nc_crm_integration_factory::create($provider_id, $site_id);

        if (!$provider) {
            $message = 'Provider not found';
        } elseif (!method_exists($provider, 'exchange_code')) {
            $message = 'Provider does not support OAuth';
        } else {
            $result = $provider->exchange_code($code);

            if ($result) {
                $success = true;
                $message = defined('NETCAT_MODULE_CRM_INTEGRATION_AUTH_SUCCESS')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AUTH_SUCCESS
                    : 'Authorization successful! You can close this window.';
            } else {
                $message = defined('NETCAT_MODULE_CRM_INTEGRATION_AUTH_FAILED')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AUTH_FAILED
                    : 'Failed to exchange authorization code for tokens';
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

// Output HTML page that shows result and optionally closes window
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CRM Authorization</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .message {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
        }
        .close-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .close-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $success ? '&#10004;' : '&#10006;'; ?>
        </div>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <button class="close-btn" onclick="closeWindow()">
            <?php echo $success ? 'Close window' : 'Close'; ?>
        </button>
    </div>
    <script>
        function closeWindow() {
            // Try to refresh parent window
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.location.reload();
                } catch (e) {}
            }
            window.close();
        }
        <?php if ($success): ?>
        // Auto-close after 3 seconds on success
        setTimeout(closeWindow, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
