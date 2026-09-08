<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['admin', 'directivo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - Spelling Bee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/dashboard_admin.css">
</head>
<body>
    <?php include __DIR__ . '/includes/partials/navbar.php'; ?>
    <div class="contenido">
        <h2>Bienvenido/a, <?= htmlspecialchars($_SESSION['nombre']) ?></h2>
        <p>Desde acá vas a poder gestionar instituciones, usuarios, competencias y el banco de palabras.</p>
        <!-- Próximos módulos: instituciones, usuarios, competencias, palabras -->
    </div>

    <script src="assets/js/utils.js"></script>
    <script src="assets/js/dashboard_admin.js"></script>
</body>
</html>
