<?php
$host = "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com";
$port = "5432";
$dbname = "sk_system";
$user = "sk_new";
$pass = "bX9G8vuFr3DTrHIASqTOsK9qCZ6A4lfZ";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>