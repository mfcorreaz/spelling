<?php
session_start();
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['error_login'] = 'Completá email y contraseña.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, nombre, apellido, email, password_hash, rol, activo FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
    $_SESSION['error_login'] = 'Email o contraseña incorrectos.';
    header('Location: index.php');
    exit;
}

if (!$usuario['activo']) {
    $_SESSION['error_login'] = 'Tu cuenta está inactiva. Consultá con el administrador.';
    header('Location: index.php');
    exit;
}

// Login correcto: guardamos datos en sesión
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['nombre'] = $usuario['nombre'];
$_SESSION['apellido'] = $usuario['apellido'];
$_SESSION['email'] = $usuario['email'];
$_SESSION['rol'] = $usuario['rol'];

// Redirige al dashboard según el rol
switch ($usuario['rol']) {
    case 'admin':
    case 'directivo':
        header('Location: dashboard_admin.php');
        break;
    case 'profesor':
        header('Location: dashboard_profesor.php');
        break;
    case 'alumno':
        header('Location: dashboard_alumno.php');
        break;
    default:
        header('Location: index.php');
}
exit;
