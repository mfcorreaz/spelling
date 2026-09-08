<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor', 'admin', 'directivo']);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/logica_torneo.php';

$competencia_id = (int)($_GET['id'] ?? 0);
$round_actual = (int)($_GET['round'] ?? 1);

$stmt = $pdo->prepare("SELECT * FROM competencias WHERE id = ?");
$stmt->execute([$competencia_id]);
$competencia = $stmt->fetch();
if (!$competencia) { die('El torneo no existe.'); }

$levels = obtener_levels_competencia($pdo, $competencia_id);
$usa_oracion = $competencia['formato'] === 'deletreo_oracion';
$max_round = obtener_max_round($pdo, $competencia_id);

$columnas = [];
foreach ($levels as $nivel) {
    $datos = obtener_resultados_de_ronda($pdo, $competencia_id, $nivel['id'], $round_actual);
    aplicar_eliminacion($pdo, $datos['filas']);
    $columnas[] = ['codigo' => $nivel['codigo'], 'filas' => $datos['filas']];
}

$torneo_finalizado = $round_actual >= $max_round;

if ($torneo_finalizado) {
    $stmt = $pdo->prepare("UPDATE competencias SET estado = 'finalizada' WHERE id = ?");
    $stmt->execute([$competencia_id]);
} else {
    $primer_nivel_id = $levels[0]['id'] ?? null;
    $url_siguiente_ronda = "panel_control.php?id=$competencia_id&nivel_id=$primer_nivel_id&round=" . ($round_actual + 1) . "&idx=0";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Round <?= $round_actual ?> - Spelling Bee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/panel_control.css">
    <link rel="stylesheet" href="assets/css/resultados_nivel.css">
    <link rel="stylesheet" href="assets/css/resumen_ronda.css">
</head>
<body class="panel-kiosko">
    <a href="ver_competencia.php?id=<?= $competencia_id ?>" class="btn-salir-panel">← Salir</a>

    <header class="panel-header">
        <h1 class="nombre-torneo"><?= htmlspecialchars($competencia['nombre']) ?></h1>
        <p class="subtitulo-round">Resumen de la Round <?= $round_actual ?></p>
    </header>

    <main class="grilla-resumen-ronda">
        <?php foreach ($columnas as $col): ?>
            <div class="columna-nivel">
                <div class="columna-nivel-titulo">
                    <span class="titulo-nivel-mini"><?= htmlspecialchars($col['codigo']) ?></span>
                    <span class="round-mini">Round <?= $round_actual ?></span>
                </div>
                <?php if (empty($col['filas'])): ?>
                    <p class="sin-datos-oscuro">Sin resultados.</p>
                <?php else: ?>
                    <ol class="lista-tiempos">
                        <?php foreach ($col['filas'] as $f): ?>
                            <li class="<?= $f['pasa'] ? 'item-pasa' : 'item-eliminado' ?>">
                                <span class="nombre-en-lista"><?= htmlspecialchars($f['apellido'] . ', ' . $f['nombre']) ?></span>
                                <span class="tiempo-en-lista"><?= number_format($f['tiempo_total'], 2) ?>s</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>

    <div class="acciones-resultados">
        <?php if ($torneo_finalizado): ?>
            <p class="mensaje-final">🏆 ¡Torneo finalizado!</p>
            <a href="ver_competencia.php?id=<?= $competencia_id ?>" class="btn-continuar">Volver al torneo</a>
        <?php else: ?>
            <a href="<?= $url_siguiente_ronda ?>" class="btn-continuar">Comenzar Round <?= $round_actual + 1 ?> →</a>
        <?php endif; ?>
    </div>

    <footer class="panel-footer-minimo">🐝 Spelling Bee</footer>
</body>
</html>
