<?php
// email_config.php - Email configuration and functions

/**
 * Generate a random password with letters, numbers, and symbols
 */
function generateRandomPassword($length = 12)
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    $password = '';
    $charactersLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $charactersLength - 1)];
    }

    return $password;
}

/**
 * Send welcome email with login credentials
 * For local testing, this will save emails to files instead of sending
 */
function sendPasswordEmail($email, $name, $password, $plan_name)
{
    // For local development, save email to file instead of sending
    if (isLocalEnvironment()) {
        return saveEmailToFile($email, $name, $password, $plan_name);
    }

    // Production email sending code
    $to = $email;
    $subject = "Bienvenue sur Syndic Way - Vos identifiants de connexion";

    $message = getEmailTemplate($name, $email, $password, $plan_name);

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Syndic Way <noreply@syndicway.com>" . "\r\n";
    $headers .= "Reply-To: support@syndicway.com" . "\r\n";

    return mail($to, $subject, $message, $headers);
}

/**
 * Check if we're in local development environment
 */
function isLocalEnvironment()
{
    return in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']) ||
        strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0;
}

/**
 * Save email to file for local testing
 */
function saveEmailToFile($email, $name, $password, $plan_name)
{
    $emails_dir = __DIR__ . '/emails';

    // Create emails directory if it doesn't exist
    if (!file_exists($emails_dir)) {
        mkdir($emails_dir, 0777, true);
    }

    $filename = $emails_dir . '/email_' . date('Y-m-d_H-i-s') . '_' . str_replace('@', '_at_', $email) . '.html';

    $content = "<!DOCTYPE html>
<html>
<head>
    <title>Email sent to: {$email}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .email-info { background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .credentials { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='email-info'>
        <h2>📧 Email Details</h2>
        <p><strong>To:</strong> {$email}</p>
        <p><strong>Name:</strong> {$name}</p>
        <p><strong>Plan:</strong> {$plan_name}</p>
        <p><strong>Sent:</strong> " . date('Y-m-d H:i:s') . "</p>
    </div>
    
    <div class='credentials'>
        <h3>🔐 Login Credentials</h3>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Password:</strong> <code style='background: #f8f9fa; padding: 5px 8px; border-radius: 3px;'>{$password}</code></p>
    </div>
    
    <hr>
    
    <h3>📄 Full Email Content:</h3>
    " . getEmailTemplate($name, $email, $password, $plan_name) . "
</body>
</html>";

    $result = file_put_contents($filename, $content);

    if ($result !== false) {
        // Log success
        error_log("LOCAL EMAIL: Saved to file {$filename} for {$email} with password: {$password}");
        return true;
    }

    return false;
}

/**
 * Get HTML email template
 */
function getEmailTemplate($name, $email, $password, $plan_name)
{
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
                background: #ffffff;
            }
            .header { 
                background: #2c5aa0; 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
                border-radius: 8px 8px 0 0;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
            }
            .content { 
                padding: 30px; 
                background: #f9f9f9; 
            }
            .credentials { 
                background: white; 
                padding: 20px; 
                border-left: 4px solid #2c5aa0; 
                margin: 25px 0; 
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .credentials h3 {
                margin-top: 0;
                color: #2c5aa0;
            }
            .password-box {
                background: #f0f0f0; 
                padding: 8px 12px; 
                font-size: 16px;
                font-family: 'Courier New', monospace;
                border-radius: 4px;
                display: inline-block;
                border: 1px solid #ddd;
            }
            .footer { 
                text-align: center; 
                padding: 20px; 
                color: #666; 
                background: #f0f0f0;
                border-radius: 0 0 8px 8px;
            }
            .btn { 
                display: inline-block; 
                background: #2c5aa0; 
                color: white; 
                padding: 12px 25px; 
                text-decoration: none; 
                border-radius: 5px; 
                font-weight: bold;
                margin: 20px 0;
            }
            .btn:hover {
                background: #1e3f73;
            }
            .steps {
                background: white;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
            .steps ol {
                margin: 0;
                padding-left: 20px;
            }
            .steps li {
                margin: 8px 0;
            }
            .warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏢 Syndic Way</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px;'>Bienvenue dans votre plateforme de gestion d'immeuble</p>
            </div>
            
            <div class='content'>
                <h2 style='color: #2c5aa0; margin-top: 0;'>Bonjour " . htmlspecialchars($name) . ",</h2>
                
                <p style='font-size: 16px;'>Félicitations ! Votre abonnement <strong>" . htmlspecialchars($plan_name) . "</strong> a été activé avec succès.</p>
                
                <div class='credentials'>
                    <h3>🔐 Vos identifiants de connexion :</h3>
                    <p><strong>Email :</strong> " . htmlspecialchars($email) . "</p>
                    <p><strong>Mot de passe :</strong> <span class='password-box'>" . htmlspecialchars($password) . "</span></p>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Important :</strong> Pour votre sécurité, nous vous recommandons fortement de changer ce mot de passe lors de votre première connexion.
                </div>
                
                <div style='text-align: center;'>
                    <a href='http://localhost/syndicplatform/public/login.php' class='btn'>Se connecter maintenant</a>
                </div>
                
                <div class='steps'>
                    <h3 style='color: #2c5aa0; margin-top: 0;'>📋 Prochaines étapes :</h3>
                    <ol>
                        <li><strong>Connectez-vous</strong> avec vos identifiants ci-dessus</li>
                        <li><strong>Changez votre mot de passe</strong> dans les paramètres</li>
                        <li><strong>Configurez votre profil</strong> et les informations de votre immeuble</li>
                        <li><strong>Invitez vos résidents</strong> à rejoindre la plateforme</li>
                        <li><strong>Commencez à gérer</strong> votre immeuble efficacement</li>
                    </ol>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 4px; margin: 20px 0;'>
                    <h3 style='color: #2c5aa0; margin-top: 0;'>🎯 Fonctionnalités disponibles :</h3>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li>Gestion des résidents et appartements</li>
                        <li>Suivi des paiements et charges</li>
                        <li>Système de maintenance et réclamations</li>
                        <li>Communication avec les résidents</li>
                        <li>Rapports et statistiques</li>
                    </ul>
                </div>
                
                <p style='font-size: 16px;'>Si vous avez des questions ou besoin d'aide, n'hésitez pas à contacter notre équipe support à <a href='mailto:support@syndicway.com'>support@syndicway.com</a></p>
                
                <p style='font-size: 16px;'>Merci de nous faire confiance pour la gestion de votre immeuble !</p>
                
                <p style='font-size: 16px;'><strong>L'équipe Syndic Way</strong></p>
            </div>
            
            <div class='footer'>
                <p style='margin: 0;'><strong>© " . date('Y') . " Syndic Way - Tous droits réservés</strong></p>
                <p style='margin: 5px 0 0 0; font-size: 12px;'>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                <p style='margin: 5px 0 0 0; font-size: 12px;'>Pour toute question, contactez-nous à support@syndicway.com</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send email notification to admin about new subscription
 */
function notifyAdminNewSubscription($member_name, $member_email, $plan_name, $amount)
{
    if (isLocalEnvironment()) {
        // For local development, just log it
        error_log("ADMIN NOTIFICATION: New subscription - {$member_name} ({$member_email}) - {$plan_name} - {$amount} DH");
        return true;
    }

    $to = "admin@syndicway.com"; // Change this to your admin email
    $subject = "Nouvel abonnement Syndic Way - " . $member_name;

    $message = "
    <h2>Nouvel abonnement activé</h2>
    <p><strong>Client :</strong> " . htmlspecialchars($member_name) . "</p>
    <p><strong>Email :</strong> " . htmlspecialchars($member_email) . "</p>
    <p><strong>Forfait :</strong> " . htmlspecialchars($plan_name) . "</p>
    <p><strong>Montant :</strong> " . number_format($amount, 2) . " DH</p>
    <p><strong>Date :</strong> " . date('Y-m-d H:i:s') . "</p>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Syndic Way System <system@syndicway.com>" . "\r\n";

    return mail($to, $subject, $message, $headers);
}

/**
 * Log email activities
 */
function logEmailActivity($email, $type, $status)
{
    $logs_dir = __DIR__ . '/logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0777, true);
    }

    $log_message = date('Y-m-d H:i:s') . " - Email: {$email}, Type: {$type}, Status: {$status}\n";
    error_log($log_message, 3, $logs_dir . '/email.log');
}

/**
 * Get list of saved email files for viewing
 */
function getSavedEmails()
{
    $emails_dir = __DIR__ . '/emails';
    if (!file_exists($emails_dir)) {
        return [];
    }

    $files = glob($emails_dir . '/email_*.html');
    $emails = [];

    foreach ($files as $file) {
        $emails[] = [
            'filename' => basename($file),
            'filepath' => $file,
            'date' => date('Y-m-d H:i:s', filemtime($file)),
            'size' => filesize($file)
        ];
    }

    // Sort by date, newest first
    usort($emails, function ($a, $b) {
        return filemtime($b['filepath']) - filemtime($a['filepath']);
    });

    return $emails;
}
?>