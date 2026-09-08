<?php
// ============================================================
// MÓDULO: NAVBAR
// Reutilizable en cualquier dashboard. Muestra el título según
// el rol de la sesión activa y los datos del usuario logueado.
// Espera recibir: $_SESSION['rol'], $_SESSION['nombre'], $_SESSION['apellido']
// ============================================================

$titulos_por_rol = [
    'admin'     => 'Admin',
    'directivo' => 'Directivo',
    'profesor'  => 'Profesor',
    'alumno'    => 'Mi panel',
];
$titulo = $titulos_por_rol[$_SESSION['rol']] ?? '';
?>
<div class="navbar">
    <div class="logo">🐝 Spelling Bee<?= $titulo ? ' — ' . $titulo : '' ?></div>
    <div class="usuario-info">
        <span><?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?></span>
        <a href="logout.php" class="salir">Salir</a>
    </div>
</div>
