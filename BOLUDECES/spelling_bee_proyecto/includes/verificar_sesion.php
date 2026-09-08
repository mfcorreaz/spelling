<?php
// ============================================================
// Incluir este archivo al principio de cualquier página protegida.
// Corta la ejecución y redirige al login si no hay sesión activa.
//
// Uso opcional con roles permitidos:
//   require_once __DIR__ . '/verificar_sesion.php';
//   verificar_rol(['admin', 'directivo']); // solo estos roles pueden entrar
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /index.php');
    exit;
}

function verificar_rol(array $roles_permitidos) {
    if (!in_array($_SESSION['rol'], $roles_permitidos, true)) {
        http_response_code(403);
        die('No tenés permiso para acceder a esta página.');
    }
}
