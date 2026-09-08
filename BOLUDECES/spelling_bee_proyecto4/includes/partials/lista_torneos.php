<?php
// ============================================================
// MÓDULO: LISTADO DE TORNEOS
// Espera recibir: $pdo, $_SESSION['usuario_id']
// Colores: verde = programado, amarillo = en curso, rojo = finalizado
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        c.id, c.nombre, c.fecha, c.estado, c.formato, c.tipo,
        GROUP_CONCAT(DISTINCT n.codigo ORDER BY n.codigo SEPARATOR ', ') AS niveles,
        (SELECT COUNT(*) FROM competencia_rounds r WHERE r.competencia_id = c.id AND r.numero_round = 1) AS cant_rounds_config,
        (SELECT COUNT(*) FROM inscripciones i WHERE i.competencia_id = c.id) AS total_alumnos
    FROM competencias c
    LEFT JOIN competencia_levels cl ON cl.competencia_id = c.id
    LEFT JOIN niveles n ON n.id = cl.nivel_id
    WHERE c.creado_por_usuario_id = ?
    GROUP BY c.id
    ORDER BY c.fecha DESC
");
$stmt->execute([$_SESSION['usuario_id']]);
$torneos = $stmt->fetchAll();

$etiquetas_estado = [
    'borrador'   => ['texto' => 'Programado', 'clase' => 'estado-verde'],
    'en_curso'   => ['texto' => 'En curso',   'clase' => 'estado-amarillo'],
    'finalizada' => ['texto' => 'Finalizado', 'clase' => 'estado-rojo'],
];
?>
<section class="modulo-torneos">
    <h3>Mis torneos</h3>

    <?php if (empty($torneos)): ?>
        <p class="sin-datos">Todavía no creaste ningún torneo. ¡Arrancá con el botón de arriba!</p>
    <?php else: ?>
        <div class="grilla-torneos">
            <?php foreach ($torneos as $t): ?>
                <?php $estado = $etiquetas_estado[$t['estado']]; ?>
                <a href="ver_competencia.php?id=<?= (int)$t['id'] ?>" class="tarjeta-torneo <?= $estado['clase'] ?>">
                    <div class="tarjeta-torneo-header">
                        <span class="badge-estado <?= $estado['clase'] ?>"><?= $estado['texto'] ?></span>
                        <span class="badge-tipo"><?= $t['tipo'] === 'simple' ? 'Simple' : 'Completo' ?></span>
                    </div>
                    <h4><?= htmlspecialchars($t['nombre']) ?></h4>
                    <p class="tarjeta-torneo-fecha"><?= date('d/m/Y', strtotime($t['fecha'])) ?></p>
                    <div class="tarjeta-torneo-detalle">
                        <span>📚 <?= htmlspecialchars($t['niveles'] ?: '—') ?></span>
                        <span>👥 <?= (int)$t['total_alumnos'] ?> alumnos</span>
                        <span>🎤 <?= $t['formato'] === 'deletreo_oracion' ? 'Deletreo + Oración' : 'Deletreo' ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
