<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['alumno']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi panel - Spelling Bee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/dashboard_alumno.css">
</head>
<body>
    <?php include __DIR__ . '/includes/partials/navbar.php'; ?>
    <div class="contenido">
        <h2>¡Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>!</h2>
        <p>Desde acá vas a poder practicar, ver tus competencias o crear una competencia simple.</p>
        <!-- Próximos módulos: practicar, mis competencias, crear competencia simple -->
    </div>

    <script src="assets/js/utils.js"></script>
    <script src="assets/js/dashboard_alumno.js"></script>
</body>
</html>
