<?php
// ============================================================
// CONEXIÓN A LA BASE DE DATOS (PDO)
// Ajustá estos datos si tu XAMPP usa otro usuario/clave
// ============================================================

$DB_HOST = 'localhost';
$DB_NAME = 'spelling_bee';
$DB_USER = 'root';
$DB_PASS = '';      // en XAMPP por defecto no tiene contraseña

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
