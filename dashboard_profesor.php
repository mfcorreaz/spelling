<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor']);
require_once __DIR__ . '/config/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Profesor - Spelling Bee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/dashboard_profesor.css">
</head>
<body>
    <?php include __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="contenido">
        <?php include __DIR__ . '/includes/partials/banner_profesor.php'; ?>
        <?php include __DIR__ . '/includes/partials/boton_crear_torneo.php'; ?>
        <?php include __DIR__ . '/includes/partials/lista_torneos.php'; ?>
    </div>

    <?php include __DIR__ . '/includes/partials/footer.php'; ?>

    <script src="assets/js/utils.js"></script>
    <script src="assets/js/dashboard_profesor.js"></script>
</body>
</html>
