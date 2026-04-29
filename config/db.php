<?php

$host = getenv("DB_HOST") ?: "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com";
$dbname = getenv("DB_NAME") ?: "sk_system";
$user = getenv("DB_USER") ?: "sk_new";
$pass = getenv("DB_PASSWORD") ?: "bX9G8vuFr3DTrHIASqTOsK9qCZ6A4lfZ";
$port = getenv("DB_PORT") ?: "5432";

/* Safety check */
if (!$host || !$dbname || !$user || !$pass) {
    die("Missing database environment variables.");
}

try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>