<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor', 'admin', 'directivo']);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/logica_torneo.php';

$competencia_id = (int)($_GET['id'] ?? 0);
$nivel_id = (int)($_GET['nivel_id'] ?? 0);
$round_actual = (int)($_GET['round'] ?? 1);

$stmt = $pdo->prepare("SELECT * FROM competencias WHERE id = ?");
$stmt->execute([$competencia_id]);
$competencia = $stmt->fetch();
if (!$competencia) { die('El torneo no existe.'); }

$stmt = $pdo->prepare("SELECT codigo FROM niveles WHERE id = ?");
$stmt->execute([$nivel_id]);
$nivel_codigo = $stmt->fetch()['codigo'] ?? '—';

$levels = obtener_levels_competencia($pdo, $competencia_id);
$usa_oracion = $competencia['formato'] === 'deletreo_oracion';

// ---- ¿Cuántos alumnos presentes debían competir este level en este round? ----
// (se calcula ANTES de aplicar la eliminación, para no descontar a los que
// ya se marcaron eliminados como resultado de ESTA ronda)
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total FROM inscripciones
    WHERE competencia_id = ? AND nivel_id = ? AND presente = 1 AND eliminado = 0
");
$stmt->execute([$competencia_id, $nivel_id]);
$total_esperados = (int)$stmt->fetch()['total'];

$datos_ronda = obtener_resultados_de_ronda($pdo, $competencia_id, $nivel_id, $round_actual);
$total_cargados = count($datos_ronda['filas']);
$faltan_tiempos = $total_esperados - $total_cargados;

// Solo se aplica la eliminación (y se habilita avanzar) si están TODOS los tiempos cargados
if ($faltan_tiempos <= 0 && $total_esperados > 0) {
    aplicar_eliminacion($pdo, $datos_ronda['filas']);
    $puede_avanzar = true;
} else {
    $puede_avanzar = false;
}

// Determinar el siguiente level en la secuencia (o si ya se terminaron todos)
$idx_nivel_actual = null;
foreach ($levels as $i => $l) { if ($l['id'] == $nivel_id) $idx_nivel_actual = $i; }
$hay_siguiente_nivel = isset($levels[$idx_nivel_actual + 1]);

if ($hay_siguiente_nivel) {
    $siguiente_nivel_id = $levels[$idx_nivel_actual + 1]['id'];
    $url_siguiente = "panel_control.php?id=$competencia_id&nivel_id=$siguiente_nivel_id&round=$round_actual&idx=0";
    $texto_siguiente = "Continuar con " . $levels[$idx_nivel_actual + 1]['codigo'] . " →";
} else {
    $url_siguiente = "resumen_ronda.php?id=$competencia_id&round=$round_actual";
    $texto_siguiente = "Ver resumen de la Round $round_actual →";
}

// Link para volver a cronometrar este mismo level si faltan tiempos
$url_volver_a_cargar = "panel_control.php?id=$competencia_id&nivel_id=$nivel_id&round=$round_actual&idx=0";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultados <?= htmlspecialchars($nivel_codigo) ?> - Round <?= $round_actual ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/panel_control.css">
    <link rel="stylesheet" href="assets/css/resultados_nivel.css">
</head>
<body class="panel-kiosko">
    <a href="ver_competencia.php?id=<?= $competencia_id ?>" class="btn-salir-panel">← Salir</a>

    <header class="panel-header">
        <h3 class="titulo-nivel-grande"><?= htmlspecialchars($nivel_codigo) ?></h3>
        <p class="subtitulo-round">Round <?= $round_actual ?> — Resultados</p>
    </header>

    <main class="contenedor-resultados">
        <?php if (empty($datos_ronda['filas'])): ?>
            <p class="sin-datos-oscuro">Todavía no hay resultados cargados para este level en esta ronda.</p>
        <?php else: ?>
            <table class="tabla-resultados">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Institución</th>
                        <th>Alumno</th>
                        <th>Deletreo</th>
                        <?php if ($usa_oracion): ?><th>Oración</th><?php endif; ?>
                        <th>Penalidad</th>
                        <th>Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos_ronda['filas'] as $f): ?>
                        <tr class="<?= $f['pasa'] ? 'fila-pasa' : 'fila-eliminado' ?>">
                            <td><?= $f['puesto'] ?></td>
                            <td><?= htmlspecialchars($f['institucion_nombre']) ?></td>
                            <td><?= htmlspecialchars($f['apellido'] . ', ' . $f['nombre']) ?></td>
                            <td><?= number_format($f['tiempo_deletreo'], 2) ?>s <?= $f['acierto_deletreo'] ? '' : '❌' ?></td>
                            <?php if ($usa_oracion): ?>
                                <td><?= $f['tiempo_oracion'] !== null ? number_format($f['tiempo_oracion'], 2) . 's' : '—' ?> <?= $f['oracion_correcta'] ? '' : '❌' ?></td>
                            <?php endif; ?>
                            <td>+<?= number_format($f['penalizacion_segundos'], 0) ?>s</td>
                            <td><strong><?= number_format($f['tiempo_total'], 2) ?>s</strong></td>
                            <td><span class="badge-resultado <?= $f['pasa'] ? 'badge-pasa' : 'badge-eliminado' ?>"><?= $f['pasa'] ? 'PASA' : 'ELIMINADO' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!$puede_avanzar): ?>
            <p class="aviso-faltan-tiempos">
                ⚠️ Faltan cargar <?= max(0, $faltan_tiempos) ?> de <?= $total_esperados ?> tiempos de este level antes de poder continuar.
            </p>
        <?php endif; ?>

        <div class="acciones-resultados">
            <?php if ($puede_avanzar): ?>
                <a href="<?= $url_siguiente ?>" id="btn-continuar" class="btn-continuar"><?= $texto_siguiente ?></a>
            <?php else: ?>
                <a href="<?= $url_volver_a_cargar ?>" class="btn-continuar btn-volver-cargar">← Volver a cargar los tiempos faltantes</a>
            <?php endif; ?>
        </div>
    </main>

    <footer class="panel-footer-minimo">🐝 Spelling Bee</footer>
    <script src="assets/js/resultados_nivel.js"></script>
</body>
</html>
