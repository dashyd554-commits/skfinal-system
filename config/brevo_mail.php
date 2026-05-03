<?php

$autoload = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoload)) {
    die("❌ Composer autoload not found. Run: composer install");
}

require_once $autoload;

use Dotenv\Dotenv;

$dotenvPath = __DIR__ . '/../';

if (!file_exists($dotenvPath . '.env')) {
    die("❌ .env file not found");
}

$dotenv = Dotenv::createImmutable($dotenvPath);
$dotenv->load();

/* ================= BREVO EMAIL FUNCTION ================= */
function sendBrevoEmail($toEmail, $toName, $subject, $htmlContent)
{
    // Get API key from .env
    $apiKey = $_ENV['BREVO_API_KEY'] ?? null;

    if (!$apiKey) {
        return [
            "success" => false,
            "message" => "Brevo API key not found in .env file"
        ];
    }

    /* ================= EMAIL PAYLOAD ================= */
    $data = [
        "sender" => [
            "name" => "SK System",
            "email" => "dummyme683@gmail.com"
        ],
        "to" => [
            [
                "email" => $toEmail,
                "name" => $toName
            ]
        ],
        "subject" => $subject,
        "htmlContent" => $htmlContent
    ];

    /* ================= CURL REQUEST ================= */
    $ch = curl_init("https://api.brevo.com/v3/smtp/email");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "api-key: " . $apiKey,
        "content-type: application/json"
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    /* ================= ERROR HANDLING ================= */
    if ($response === false) {
        return [
            "success" => false,
            "message" => "cURL Error: " . $error
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            "success" => true,
            "message" => "Email sent successfully",
            "response" => $decoded
        ];
    }

    return [
        "success" => false,
        "message" => "Failed to send email",
        "http_code" => $httpCode,
        "response" => $decoded
    ];
}