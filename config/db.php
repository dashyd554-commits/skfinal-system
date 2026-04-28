<?php

$host = getenv("DB_HOST"); "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com";
$dbname = getenv("DB_NAME"); "sk_system";
$user = getenv("DB_USER"); "sk_admin" ;
$pass = getenv("DB_PASSWORD"); "vnEwS9NI5pkc7khmhNCMfvbjbID5YAtm" ;
$port = getenv("DB_PORT") ?: "5432";

/* Safety check (prevents silent crash) */
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