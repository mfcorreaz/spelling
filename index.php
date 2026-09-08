<?php
session_start();

// Si ya hay sesión activa, redirige directo a su dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard_' . $_SESSION['rol'] . '.php');
    exit;
}

$error = $_SESSION['error_login'] ?? '';
unset($_SESSION['error_login']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingresar - Spelling Bee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="contenedor-centrado">
        <div class="tarjeta">
            <h1>Spelling Bee</h1>
            <p class="subtitulo">Ingresá con tu cuenta para continuar</p>

            <?php if ($error): ?>
                <div class="alerta-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="procesar_login.php" method="POST">
                <div class="campo">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="campo">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Ingresar</button>
            </form>
        </div>
    </div>

    <script src="assets/js/utils.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>
