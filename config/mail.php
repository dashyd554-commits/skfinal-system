<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $body) {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // ✅ Gmail account
        $mail->Username = 'dashyd554@gmail.com';

        // ⚠️ MUST BE APP PASSWORD (NOT NORMAL PASSWORD)
        $mail->Password = 'stji hivv qglc sdsx';

        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // MUST match Gmail account
        $mail->setFrom('dashyd554@gmail.com', 'skdss');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        return $mail->send();

    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}