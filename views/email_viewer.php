<?php
require_once __DIR__ . '/../email_config.php';

$emails = getSavedEmails();
$view_email = $_GET['view'] ?? null;

// Function to extract credentials from email content
function extractCredentialsFromEmail($content) {
    $credentials = ['email' => '', 'password' => ''];
    
    // Try to find email in content
    if (preg_match('/Email:\s*([^\s<]+@[^\s<]+)/i', $content, $matches)) {
        $credentials['email'] = trim($matches[1]);
    } elseif (preg_match('/(?:email|e-mail).*?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $content, $matches)) {
        $credentials['email'] = trim($matches[1]);
    }
    
    // Try to find password in content (various formats)
    if (preg_match('/(?:Password|Mot de passe|Votre mot de passe):\s*([^\s<]+)/i', $content, $matches)) {
        $credentials['password'] = trim($matches[1]);
    } elseif (preg_match('/<strong[^>]*>([A-Za-z0-9!@#$%^&*()_+\-=\[\]{}|;:,.<>?]{8,})<\/strong>/', $content, $matches)) {
        // Look for password in strong tags (common in email templates)
        $credentials['password'] = trim($matches[1]);
    } elseif (preg_match('/(?:password|mot de passe).*?([A-Za-z0-9!@#$%^&*()_+\-=\[\]{}|;:,.<>?]{8,})/i', $content, $matches)) {
        $credentials['password'] = trim($matches[1]);
    }
    
    return $credentials;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Viewer - Syndic Way</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .email-list { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .email-item { background: white; padding: 15px; margin: 10px 0; border-radius: 4px; border: 1px solid #dee2e6; }
        .email-item h4 { margin: 0 0 10px 0; color: #007bff; }
        .email-item p { margin: 5px 0; color: #666; }
        .btn { padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; border: none; cursor: pointer; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-info { background: #17a2b8; }
        .email-viewer { background: white; border: 1px solid #dee2e6; border-radius: 8px; margin: 20px 0; }
        .no-emails { text-align: center; padding: 40px; color: #666; }
        .stats { background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .credentials-box { background: #fff3cd; padding: 20px; border-radius: 8px; margin: 15px; border-left: 4px solid #ffc107; }
        .credentials-highlight { background: #d4edda; padding: 20px; border-radius: 8px; margin: 15px; border-left: 4px solid #28a745; }
        .password-display { font-family: 'Courier New', monospace; font-size: 16px; background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0; border: 2px solid #007bff; word-break: break-all; }
        .copy-btn { background: #6c757d; color: white; padding: 4px 8px; font-size: 12px; border: none; border-radius: 3px; cursor: pointer; margin-left: 10px; }
        .quick-actions { background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 15px; text-align: center; }
        .success-message { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Viewer - Local Development</h1>
        
        <?php if ($view_email): ?>
            <?php 
            $email_file = __DIR__ . '/../emails/' . basename($view_email);
            if (file_exists($email_file)): 
                $email_content = file_get_contents($email_file);
                $credentials = extractCredentialsFromEmail($email_content);
            ?>
                <div class="email-viewer">
                    <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                        <a href="?" class="btn">← Back to Email List</a>
                        <h3 style="margin: 10px 0; display: inline-block;">Viewing: <?php echo htmlspecialchars($view_email); ?></h3>
                        <a href="<?php echo '../emails/' . basename($view_email); ?>" class="btn btn-success" target="_blank">Open in New Tab</a>
                    </div>
                    
                    <?php if (!empty($credentials['email']) || !empty($credentials['password'])): ?>
                    <div class="credentials-highlight">
                        <h4>🔐 Login Credentials Found!</h4>
                        <?php if (!empty($credentials['email'])): ?>
                        <p><strong>Email:</strong></p>
                        <div class="password-display"><?php echo htmlspecialchars($credentials['email']); ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($credentials['email']); ?>')">Copy</button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($credentials['password'])): ?>
                        <p><strong>Password:</strong></p>
                        <div class="password-display"><?php echo htmlspecialchars($credentials['password']); ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($credentials['password']); ?>')">Copy</button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="quick-actions">
                            <button class="btn btn-success" onclick="autoFillLogin()">🚀 Auto-Fill & Open Login</button>
                            <a href="../public/login.php" class="btn btn-info">Go to Login Page</a>
                            <button class="btn btn-warning" onclick="copyBothCredentials()">Copy Both Credentials</button>
                        </div>
                        
                        <div id="copy-success" class="success-message" style="display: none;">
                            ✅ Credentials copied to clipboard!
                        </div>
                    </div>
                    <?php else: ?>
                    <?php
                    // Fallback: Try to extract email from filename
                    if (preg_match('/email_.*?_(.+?)_at_/', $view_email, $matches)) {
                        $email_address = str_replace('_at_', '@', $matches[1]);
                        echo "<div class='credentials-box'>";
                        echo "<h4>📧 Email Recipient Info</h4>";
                        echo "<p><strong>Email:</strong> <code>{$email_address}</code>";
                        echo "<button class='copy-btn' onclick='copyToClipboard(\"{$email_address}\")'>Copy</button></p>";
                        echo "<p>Look for password in the email content below</p>";
                        echo "<a href='../public/login.php' class='btn' style='margin-top: 10px;'>Go to Login Page</a>";
                        echo "</div>";
                    }
                    ?>
                    <?php endif; ?>
                    
                    <iframe src="data:text/html;charset=utf-8,<?php echo rawurlencode($email_content); ?>" 
                            width="100%" height="700" style="border: none;"></iframe>
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;">
                    ❌ Email file not found!
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="stats">
                <h3>📊 Email Statistics</h3>
                <p><strong>Total Emails:</strong> <?php echo count($emails); ?></p>
                <p><strong>Storage Location:</strong> <code><?php echo realpath(__DIR__ . '/../emails') ?: __DIR__ . '/../emails'; ?></code></p>
                <?php if (!empty($emails)): ?>
                <p><strong>Latest Email:</strong> <?php echo $emails[0]['date']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="email-list">
                <h2>📬 Saved Emails</h2>
                <p>All emails sent in local development are saved here instead of being actually sent.</p>
                
                <?php if (empty($emails)): ?>
                    <div class="no-emails">
                        <h3>📭 No emails found</h3>
                        <p>Complete a purchase to generate test emails.</p>
                        <a href="../public/?page=subscriptions" class="btn btn-success">Go to Subscriptions</a>
                        <a href="test_email.php" class="btn btn-info">Test Email Function</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($emails as $email): ?>
                        <div class="email-item">
                            <h4><?php echo htmlspecialchars($email['filename']); ?></h4>
                            <p><strong>Date:</strong> <?php echo $email['date']; ?></p>
                            <p><strong>Size:</strong> <?php echo number_format($email['size']); ?> bytes</p>
                            <?php
                            // Try to extract email address from filename
                            if (preg_match('/email_.*?_(.+?)_at_/', $email['filename'], $matches)) {
                                $email_address = str_replace('_at_', '@', $matches[1]);
                                echo "<p><strong>Recipient:</strong> <code>{$email_address}</code></p>";
                            }
                            
                            // Try to extract credentials from file content for preview
                            $file_path = __DIR__ . '/../emails/' . $email['filename'];
                            if (file_exists($file_path)) {
                                $content = file_get_contents($file_path);
                                $creds = extractCredentialsFromEmail($content);
                                if (!empty($creds['password'])) {
                                    echo "<p><strong>🔑 Password:</strong> <code style='background: #fff3cd; padding: 2px 6px; border-radius: 3px; font-family: monospace;'>{$creds['password']}</code></p>";
                                }
                            }
                            ?>
                            <a href="?view=<?php echo urlencode($email['filename']); ?>" class="btn">View Email</a>
                            <a href="<?php echo '../emails/' . $email['filename']; ?>" class="btn btn-success" target="_blank">Open in New Tab</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <p><a href="test_accounts.php" class="btn">← Back to Test Accounts</a></p>
            <p><a href="../public/login.php" class="btn btn-success">Go to Login Page</a></p>
            <p><a href="../public/?page=subscriptions" class="btn btn-info">Create New Purchase</a></p>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showSuccessMessage('Copied: ' + text);
            }).catch(function(err) {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showSuccessMessage('Copied: ' + text);
            });
        }

        function autoFillLogin() {
            const email = '<?php echo addslashes($credentials['email'] ?? ''); ?>';
            const password = '<?php echo addslashes($credentials['password'] ?? ''); ?>';
            
            if (email && password) {
                // Store in sessionStorage for the login page to pick up
                sessionStorage.setItem('auto_fill_email', email);
                sessionStorage.setItem('auto_fill_password', password);
                window.open('../public/login.php', '_blank');
                showSuccessMessage('Opening login page with auto-filled credentials...');
            } else {
                alert('Could not extract credentials from email');
            }
        }

        function copyBothCredentials() {
            const email = '<?php echo addslashes($credentials['email'] ?? ''); ?>';
            const password = '<?php echo addslashes($credentials['password'] ?? ''); ?>';
            
            if (email && password) {
                const text = `Email: ${email}\nPassword: ${password}`;
                copyToClipboard(text);
            } else {
                alert('Could not extract credentials from email');
            }
        }

        function showSuccessMessage(message) {
            const successDiv = document.getElementById('copy-success');
            if (successDiv) {
                successDiv.textContent = '✅ ' + message;
                successDiv.style.display = 'block';
                setTimeout(() => {
                    successDiv.style.display = 'none';
                }, 3000);
            } else {
                alert(message);
            }
        }
    </script>
</body>
</html>