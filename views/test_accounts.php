<?php
// test_accounts.php - Create this file for testing accounts
session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html>

<head>
    <title>Test Login Accounts - Syndic Way</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .reset-form {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .btn {
            padding: 8px 16px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: black;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .form-group {
            margin: 15px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 300px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .credentials {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .quick-login {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .email-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }

        .workflow {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔧 Test Login Accounts - Syndic Way</h1>

        <?php
        // Handle form submissions
        if ($_POST) {
            if (isset($_POST['create_test'])) {
                // Create test account
                $test_email = "test@test.com";
                $test_password = "test123";
                $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);

                try {
                    // Check if test account exists
                    $stmt = $conn->prepare("SELECT id_member FROM member WHERE email = ?");
                    $stmt->execute([$test_email]);

                    if (!$stmt->fetchColumn()) {
                        // Create test account
                        $stmt = $conn->prepare("INSERT INTO member (full_name, email, password, phone, role, status)
                                                VALUES (?, ?, ?, ?, 2, 'active')");
                        $stmt->execute(['Test User', $test_email, $hashed_password, '123456789']);

                        echo "<div class='alert alert-success'>✅ Test account created successfully!</div>";
                    } else {
                        echo "<div class='alert alert-info'>ℹ️ Test account already exists.</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
                }
            }

            if (isset($_POST['reset_password'])) {
                // Reset password
                $member_id = $_POST['member_id'];
                $new_password = $_POST['new_password'];
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);

                try {
                    $stmt = $conn->prepare("UPDATE member SET password = ? WHERE id_member = ?");
                    $stmt->execute([$hashed, $member_id]);

                    $stmt = $conn->prepare("SELECT email, full_name FROM member WHERE id_member = ?");
                    $stmt->execute([$member_id]);
                    $user = $stmt->fetch();

                    echo "<div class='alert alert-success'>✅ Password updated successfully for " . htmlspecialchars($user['full_name']) . "!</div>";
                    echo "<div class='credentials'>";
                    echo "<strong>Updated Login Credentials:</strong><br>";
                    echo "Email: <code>{$user['email']}</code><br>";
                    echo "Password: <code>{$new_password}</code>";
                    echo "</div>";
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
                }
            }
        }
        ?>

        <!-- Complete Testing Workflow -->
        <div class="workflow">
            <h3>🔄 Complete Testing Workflow</h3>
            <ol>
                <li><strong>Method 1 - Quick Test Account:</strong> Use the "Create Test Account" button below</li>
                <li><strong>Method 2 - Real Purchase Flow:</strong> <a href="../public/?page=subscriptions"
                        class="btn btn-info">Go to Subscriptions</a></li>
                <li><strong>Check Generated Emails:</strong> <a href="email_viewer.php" class="btn btn-info">View Saved
                        Emails</a></li>
                <li><strong>Get Login Credentials:</strong> Click "Auto-Fill & Open Login" in the email viewer</li>
                <li><strong>Test Login:</strong> <a href="../public/login.php" class="btn btn-primary">Go to Login</a>
                </li>
            </ol>

            <div style="background: #e8f5e8; padding: 15px; border-radius: 4px; margin-top: 15px;">
                <strong>💡 Pro Tip:</strong> The email viewer can automatically fill login credentials for testing!
            </div>
        </div>

        <!-- Quick Login Section -->
        <div class="quick-login">
            <h3>🚀 Quick Test Login</h3>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="create_test" value="1">
                <button type="submit" class="btn btn-success">Create Test Account</button>
            </form>
            <div style="margin-top: 10px;">
                <strong>Default Test Credentials:</strong><br>
                Email: <code>test@test.com</code><br>
                Password: <code>test123</code><br>
                <a href="../public/login.php" class="btn btn-primary">Go to Login Page</a>
            </div>
        </div>

        <!-- Email Information Section -->
        <div class="email-info">
            <h3>📧 Email System Info</h3>
            <p><strong>Local Development Mode:</strong> Emails are saved as files instead of being sent.</p>
            <p><strong>Location:</strong> <code><?php echo __DIR__ . '/../emails/'; ?></code></p>
            <a href="email_viewer.php" class="btn btn-info">📧 View Saved Emails</a>
            <a href="test_email.php" class="btn btn-info">🧪 Test Email Function</a>
        </div>

        <!-- Recent Members Table -->
        <h2>📋 Recent Members (Last 10)</h2>
        <?php
        $stmt = $conn->prepare("
            SELECT m.*, s.name_subscription, ams.date_payment
            FROM member m 
            LEFT JOIN admin_member_subscription ams ON ams.id_member = m.id_member
            LEFT JOIN subscription s ON s.id_subscription = ams.id_subscription
            ORDER BY m.id_member DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Plan</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($members as $member): ?>
                <tr>
                    <td><?php echo $member['id_member']; ?></td>
                    <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                    <td><?php echo htmlspecialchars($member['phone']); ?></td>
                    <td><?php echo htmlspecialchars($member['name_subscription'] ?? 'No Plan'); ?></td>
                    <td>
                        <span style="color: <?php echo $member['status'] === 'active' ? 'green' : 'orange'; ?>;">
                            <?php echo htmlspecialchars($member['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($member['date_created'])); ?></td>
                    <td>
                        <button
                            onclick="resetPassword(<?php echo $member['id_member']; ?>, '<?php echo htmlspecialchars($member['email']); ?>')"
                            class="btn btn-warning">Reset Password</button>
                        <button
                            onclick="loginAs(<?php echo $member['id_member']; ?>, '<?php echo htmlspecialchars($member['email']); ?>')"
                            class="btn btn-info">Login As</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Password Reset Form -->
        <div class="reset-form">
            <h3>🔐 Reset Password for Testing</h3>
            <form method="POST" id="resetForm">
                <div class="form-group">
                    <label for="member_select">Select Member:</label>
                    <select name="member_id" id="member_select" required>
                        <option value="">Choose a member...</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id_member']; ?>">
                                <?php echo htmlspecialchars($member['full_name']) . ' (' . htmlspecialchars($member['email']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="text" name="new_password" id="new_password" placeholder="Enter new password" required>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
            </form>
        </div>

        <!-- Log Checking Section -->
        <div class="reset-form">
            <h3>📝 Check Logs & Debug</h3>
            <p>Check these locations for system logs:</p>
            <ul>
                <li><strong>XAMPP Error Log:</strong> <code>C:\xampp\apache\logs\error.log</code></li>
                <li><strong>PHP Error Log:</strong> Check your php.ini error_log setting</li>
                <li><strong>Email Log:</strong> <code><?php echo __DIR__; ?>/../logs/email.log</code></li>
                <li><strong>Saved Emails:</strong> <code><?php echo __DIR__; ?>/../emails/</code></li>
            </ul>
            <button onclick="checkLogs()" class="btn btn-info">Show Debug Info</button>
            <a href="email_viewer.php" class="btn btn-success">View Email Files</a>
        </div>
    </div>

    <script>
        function resetPassword(memberId, email) {
            document.getElementById('member_select').value = memberId;
            document.getElementById('new_password').value = 'test123';
            document.getElementById('new_password').focus();
        }

        function loginAs(memberId, email) {
            // Set a simple test password for this user
            if (confirm(`Reset password for ${email} to 'test123' and open login page?`)) {
                // Reset password via AJAX (you could implement this)
                // For now, just open login and show the email
                sessionStorage.setItem('auto_fill_email', email);
                sessionStorage.setItem('auto_fill_password', 'test123');
                window.open('../public/login.php', '_blank');
            }
        }

        function checkLogs() {
            const debugInfo = `
Debug Information:
================

1. Email Directory: <?php echo __DIR__ . '/../emails/'; ?>
2. Config File: <?php echo __DIR__ . '/../email_config.php'; ?>
3. Database Status: <?php echo isset($conn) ? 'Connected' : 'Not Connected'; ?>
4. PHP Version: <?php echo phpversion(); ?>

Recent Members Count: <?php echo count($members); ?>

Troubleshooting Steps:
- Check if emails directory exists and is writable
- Look for saved email files in the emails directory
- Use the Email Viewer to see generated emails
- Check XAMPP error logs for any PHP errors

For more help, check the email viewer tool.
            `;
            alert(debugInfo);
        }

        // Keyboard shortcuts for testing
        document.addEventListener('keydown', function (e) {
            // Ctrl+Shift+E = Open Email Viewer
            if (e.ctrlKey && e.shiftKey && e.key === 'E') {
                window.open('email_viewer.php', '_blank');
                e.preventDefault();
            }

            // Ctrl+Shift+L = Open Login
            if (e.ctrlKey && e.shiftKey && e.key === 'L') {
                window.open('../public/login.php', '_blank');
                e.preventDefault();
            }

            // Ctrl+Shift+S = Open Subscriptions
            if (e.ctrlKey && e.shiftKey && e.key === 'S') {
                window.open('../public/?page=subscriptions', '_blank');
                e.preventDefault();
            }
        });

        // Show keyboard shortcuts info
        console.log(`
🚀 Keyboard Shortcuts:
Ctrl+Shift+E = Open Email Viewer
Ctrl+Shift+L = Open Login Page
Ctrl+Shift+S = Open Subscriptions Page
        `);
    </script>
</body>

</html>