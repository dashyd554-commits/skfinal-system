<?php

function sendBrevoEmail($toEmail, $toName, $subject, $htmlContent){

    $apiKey = "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";

    $data = [
        "sender" => [
            "name" => "SK Decision Support System",
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

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.brevo.com/v3/smtp/email");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "api-key: ".$apiKey,
        "content-type: application/json"
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
?>