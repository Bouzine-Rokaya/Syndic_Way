<?php
// test_email.php - Test email functionality
require_once __DIR__ . '/../email_config.php';

$result = '';
$email_sent = false;

if ($_POST) {
    $test_email = $_POST['email'];
    $test_name = $_POST['name'] ?? 'Test User';
    $test_password = generateRandomPassword(); // Use the function from email_config.php
    $test_plan = 'Test Plan';
    
    // Test email sending
    $email_sent = sendPasswordEmail($test_email, $test_name, $test_password, $test_plan);
    
    if ($email_sent) {
        $result = "✅ Email sent successfully to {$test_email}! Check the email viewer to see the saved email.";
    } else {
        $result = "❌ Failed to send email to {$test_email}. Check your mail configuration.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Test - Syndic Way</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 12px 24px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-success { background: #28a745; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .success-actions { background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: center; }
        .password-info { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Testing Tool</h1>
        
        <?php if ($result): ?>
        <div class="alert <?php echo $email_sent ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo $result; ?>
        </div>
        
        <?php if ($email_sent): ?>
        <div class="success-actions">
            <h4>✅ Test Email Sent Successfully!</h4>
            <p>The email has been saved to a file. You can now:</p>
            <a href="email_viewer.php" class="btn btn-success">View Saved Email</a>
            <a href="../public/login.php" class="btn">Test Login</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Test Email Address:</label>
                <input type="email" name="email" id="email" value="<?php echo $_POST['email'] ?? 'test@example.com'; ?>" required>
            </div>
            <div class="form-group">
                <label for="name">Test Name:</label>
                <input type="text" name="name" id="name" value="<?php echo $_POST['name'] ?? 'Test User'; ?>" required>
            </div>
            <button type="submit" class="btn">Send Test Email</button>
        </form>
        
        <div class="password-info">
            <h4>🔐 Password Generation</h4>
            <p>This test uses the same <code>generateRandomPassword()</code> function as your real system, ensuring realistic testing conditions.</p>
            <p>Each test generates a new random password with letters, numbers, and special characters.</p>
        </div>
        
        <div class="info-box">
            <h3>🔧 Local Development Email System</h3>
            <p>Since you're running in local development mode, emails are automatically saved as HTML files instead of being sent via SMTP.</p>
            
            <h4>📍 Saved Email Location:</h4>
            <p><code><?php echo realpath(__DIR__ . '/../emails') ?: __DIR__ . '/../emails'; ?></code></p>
            
            <h4>🎯 What happens when you send a test email:</h4>
            <ul>
                <li>✅ Random password is generated using <code>generateRandomPassword()</code></li>
                <li>✅ Email is saved as an HTML file</li>
                <li>✅ Login credentials are included in the email</li>
                <li>✅ You can view it in the Email Viewer</li>
                <li>✅ No SMTP configuration needed</li>
            </ul>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="email_viewer.php" class="btn btn-success">📧 View All Saved Emails</a>
            </div>
        </div>
        
        <div class="info-box">
            <h3>🚀 For Production Use:</h3>
            <ul>
                <li><strong>Gmail SMTP:</strong> Use Gmail's SMTP server</li>
                <li><strong>SendGrid:</strong> Professional email service</li>
                <li><strong>Mailgun:</strong> Developer-friendly email API</li>
                <li><strong>PHP mail():</strong> Configure your server's mail function</li>
            </ul>
        </div>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="test_accounts.php">← Back to Test Accounts</a>
        </p>
    </div>
</body>
</html>