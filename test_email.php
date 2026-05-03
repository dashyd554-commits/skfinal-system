<?php
include 'config/brevo_mail.php';

$response = sendBrevoEmail(
    "your_receiver_email@gmail.com",
    "Test User",
    "SK System Test Email",
    "<h2>This is a test email from SK System 🚀</h2>"
);

echo "<pre>";
print_r($response);
echo "</pre>";
?>