<?php
// ============================================================
// MÓDULO: BANNER DE BIENVENIDA (profesor)
// Espera recibir: $pdo, $_SESSION['usuario_id'], $_SESSION['nombre']
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(estado = 'borrador')   AS programados,
        SUM(estado = 'en_curso')   AS en_curso,
        SUM(estado = 'finalizada') AS finalizados
    FROM competencias
    WHERE creado_por_usuario_id = ?
");
$stmt->execute([$_SESSION['usuario_id']]);
$stats = $stmt->fetch();
?>
<section class="banner">
    <div class="banner-texto">
        <h2>¡Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>! 👋</h2>
        <p>Este es el resumen de tus torneos de Spelling Bee.</p>
    </div>
    <div class="banner-stats">
        <div class="stat">
            <span class="stat-numero"><?= (int)$stats['total'] ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat stat-verde">
            <span class="stat-numero"><?= (int)$stats['programados'] ?></span>
            <span class="stat-label">Programados</span>
        </div>
        <div class="stat stat-amarillo">
            <span class="stat-numero"><?= (int)$stats['en_curso'] ?></span>
            <span class="stat-label">En curso</span>
        </div>
        <div class="stat stat-rojo">
            <span class="stat-numero"><?= (int)$stats['finalizados'] ?></span>
            <span class="stat-label">Finalizados</span>
        </div>
    </div>
</section>
