<?php
$page_title = "Purchase Successful - Syndic Way";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <section class="success-page">
        <div class="container">
            <div class="success-content">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <h1>Purchase Successful!</h1>
                <p class="success-message">Thank you for choosing Syndic Way. Your subscription has been activated.</p>
                
                <div class="purchase-details">
                    <h3>Purchase Details</h3>
                    <div class="detail-row">
                        <span>Plan:</span>
                        <span><?php echo htmlspecialchars($purchase['plan_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Company:</span>
                        <span><?php echo htmlspecialchars($purchase['company_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Amount:</span>
                        <span><?php echo number_format($purchase['amount_paid'], 2); ?>DH</span>
                    </div>
                    <div class="detail-row">
                        <span>Purchase Date:</span>
                        <span><?php echo date('F j, Y', strtotime($purchase['purchase_date'])); ?></span>
                    </div>
                </div>
                
                <div class="next-steps">
                    <h3>What's Next?</h3>
                    <div class="steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Account Creation</h4>
                                <p>Our team will create your syndicate account within 24 hours.</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Receive Credentials</h4>
                                <p>You'll receive login credentials and setup instructions via email.</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Start Managing</h4>
                                <p>Log in and start managing your building efficiently!</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-primary">Return to Home</a>
                    <a href="mailto:support@syndicate.com" class="btn btn-secondary">Contact Support</a>
                </div>
            </div>
        </div>
    </section>

    <style>
        .success-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .success-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 2rem;
        }
        
        .success-content h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .success-message {
            font-size: 1.2rem;
            margin-bottom: 3rem;
            opacity: 0.9;
        }
        
        .purchase-details {
            background: rgba(255,255,255,0.1);
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 3rem;
            text-align: left;
        }
        
        .purchase-details h3 {
           text-align: center;
           margin-bottom: 1.5rem;
           color: white;
       }
       
       .detail-row {
           display: flex;
           justify-content: space-between;
           padding: 0.75rem 0;
           border-bottom: 1px solid rgba(255,255,255,0.2);
       }
       
       .detail-row:last-child {
           border-bottom: none;
       }
       
       .next-steps {
           margin-bottom: 3rem;
       }
       
       .next-steps h3 {
           margin-bottom: 2rem;
       }
       
       .steps {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
           gap: 2rem;
           text-align: left;
       }
       
       .step {
           display: flex;
           align-items: flex-start;
           gap: 1rem;
       }
       
       .step-number {
           background: rgba(255,255,255,0.2);
           color: white;
           width: 40px;
           height: 40px;
           border-radius: 50%;
           display: flex;
           align-items: center;
           justify-content: center;
           font-weight: bold;
           flex-shrink: 0;
       }
       
       .step-content h4 {
           margin-bottom: 0.5rem;
           color: white;
       }
       
       .step-content p {
           opacity: 0.9;
           margin: 0;
       }
       
       .action-buttons {
           display: flex;
           gap: 1rem;
           justify-content: center;
           flex-wrap: wrap;
       }
       
       @media (max-width: 768px) {
           .success-content h1 {
               font-size: 2rem;
           }
           
           .success-icon {
               font-size: 3rem;
           }
           
           .purchase-details {
               text-align: center;
           }
           
           .detail-row {
               flex-direction: column;
               text-align: center;
               gap: 0.25rem;
           }
           
           .steps {
               grid-template-columns: 1fr;
               text-align: center;
           }
           
           .step {
               flex-direction: column;
               align-items: center;
               text-align: center;
           }
           
           .action-buttons {
               flex-direction: column;
               align-items: center;
           }
       }
   </style>
</body>
</html>