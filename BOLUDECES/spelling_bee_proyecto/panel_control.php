<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor', 'admin', 'directivo']);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/logica_torneo.php';

$competencia_id = (int)($_GET['id'] ?? 0);

// ---- Competencia + institución organizadora ----
$stmt = $pdo->prepare("
    SELECT c.*, i.nombre AS institucion_nombre, i.logo_url AS institucion_logo
    FROM competencias c
    LEFT JOIN instituciones i ON i.id = c.institucion_organizadora_id
    WHERE c.id = ?
");
$stmt->execute([$competencia_id]);
$competencia = $stmt->fetch();
if (!$competencia) { die('El torneo que buscás no existe.'); }

// ---- Level activo (por GET, o el primero configurado) ----
$stmt = $pdo->prepare("
    SELECT n.id, n.codigo FROM competencia_levels cl
    JOIN niveles n ON n.id = cl.nivel_id
    WHERE cl.competencia_id = ? ORDER BY n.codigo
");
$stmt->execute([$competencia_id]);
$levels = $stmt->fetchAll();
$nivel_id = (int)($_GET['nivel_id'] ?? ($levels[0]['id'] ?? 0));
$nivel_codigo = '—';
foreach ($levels as $l) { if ($l['id'] == $nivel_id) $nivel_codigo = $l['codigo']; }

// ---- Round activo (por GET, default 1) ----
$round_actual = (int)($_GET['round'] ?? 1);

// ---- Lista de participantes presentes y no eliminados de este nivel ----
$stmt = $pdo->prepare("
    SELECT i.id AS inscripcion_id, a.nombre, a.apellido, a.anio,
           inst.nombre AS institucion_nombre, inst.logo_url AS institucion_logo
    FROM inscripciones i
    JOIN alumnos a ON a.id = i.alumno_id
    JOIN instituciones inst ON inst.id = i.institucion_id
    WHERE i.competencia_id = ? AND i.nivel_id = ? AND i.presente = 1 AND i.eliminado = 0
    ORDER BY inst.nombre, a.apellido, a.nombre
");
$stmt->execute([$competencia_id, $nivel_id]);
$participantes = $stmt->fetchAll();

$idx = max(0, (int)($_GET['idx'] ?? 0));
$idx = min($idx, max(0, count($participantes) - 1));

$actual = $participantes[$idx] ?? null;
$anterior = $participantes[$idx - 1] ?? null;
$siguiente = $participantes[$idx + 1] ?? null;

// URL del botón/flecha "siguiente": si es el último alumno del nivel,
// en vez de avanzar el idx, va a la pantalla de resultados de ese nivel/round
if ($siguiente) {
    $url_siguiente = "panel_control.php?id=$competencia_id&nivel_id=$nivel_id&round=$round_actual&idx=" . ($idx + 1);
} elseif ($actual) {
    $url_siguiente = "resultados_nivel.php?id=$competencia_id&nivel_id=$nivel_id&round=$round_actual";
} else {
    $url_siguiente = null;
}
$url_anterior = $anterior ? "panel_control.php?id=$competencia_id&nivel_id=$nivel_id&round=$round_actual&idx=" . ($idx - 1) : null;

$usa_oracion = $competencia['formato'] === 'deletreo_oracion';

// ---- ID del round configurado (para poder guardar el resultado) ----
$stmt = $pdo->prepare("SELECT id FROM competencia_rounds WHERE competencia_id = ? AND nivel_id = ? AND numero_round = ?");
$stmt->execute([$competencia_id, $nivel_id, $round_actual]);
$round_row = $stmt->fetch();
$competencia_round_id = $round_row['id'] ?? null;

// ---- Palabra asignada al alumno actual (elegida al azar, NO se muestra en pantalla:
//      la dice el jurado en voz alta, como corresponde en un spelling bee real) ----
$palabra_id = null;
if ($actual) {
    $stmt = $pdo->prepare("
        SELECT id FROM palabras
        WHERE nivel_id = ? AND (competencia_id = ? OR competencia_id IS NULL)
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([$nivel_id, $competencia_id]);
    $palabra_row = $stmt->fetch();
    $palabra_id = $palabra_row['id'] ?? null;
}

// ---- ¿Ya hay un resultado guardado (parcial o final) para este alumno en este round?
//      Si el jurado se fue a otro registro sin querer, al volver retoma desde acá. ----
$resultado_existente = null;
if ($actual && $competencia_round_id) {
    $stmt = $pdo->prepare("
        SELECT id, tiempo_deletreo, tiempo_oracion, acierto_deletreo, penalizacion_segundos, palabra_id
        FROM resultados WHERE inscripcion_id = ? AND competencia_round_id = ?
    ");
    $stmt->execute([$actual['inscripcion_id'], $competencia_round_id]);
    $resultado_existente = $stmt->fetch();
    // Si ya había una palabra asignada guardada, la reusamos (no cambiar la palabra a mitad de camino)
    if ($resultado_existente) {
        $palabra_id = $resultado_existente['palabra_id'];
    }
}

function icono_generico() {
    return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zm0 10.09L4.5 9 12 4.91 19.5 9 12 13.09zM5 13.18v4.09c0 1.5 3.13 3.73 7 3.73s7-2.23 7-3.73v-4.09l-7 3.82-7-3.82z"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($competencia['nombre']) ?> - Panel de control</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/panel_control.css">
</head>
<body class="panel-kiosko">

    <a href="ver_competencia.php?id=<?= $competencia_id ?>" class="btn-salir-panel">← Salir</a>

    <!-- ===================== ENCABEZADO ===================== -->
    <header class="panel-header">
        <div class="institucion-organizadora">
            <span class="icono-institucion"><?= icono_generico() ?></span>
            <h3><?= htmlspecialchars($competencia['institucion_nombre'] ?? 'Spelling Bee') ?></h3>
        </div>
        <h1 class="nombre-torneo"><?= htmlspecialchars($competencia['nombre']) ?></h1>

        <?php if ($actual): ?>
        <div class="participante-actual">
            <span class="icono-institucion"><?= icono_generico() ?></span>
            <span><?= htmlspecialchars($actual['institucion_nombre']) ?></span>
            <span class="separador">—</span>
            <strong><?= htmlspecialchars($actual['apellido'] . ', ' . $actual['nombre']) ?></strong>
            <span class="separador">—</span>
            <span><?= (int)$actual['anio'] ?>° año</span>
        </div>
        <?php else: ?>
        <div class="participante-actual">No hay participantes presentes para este level.</div>
        <?php endif; ?>
    </header>

    <!-- ===================== FILA DE CARDS + CRONÓMETRO ===================== -->
    <main class="panel-principal">
        <div class="fila-cards">
            <div class="card-mini">
                <h4>Round</h4>
                <p id="valor-round"><?= $round_actual ?></p>
            </div>
            <div class="card-mini">
                <h4>Level</h4>
                <p id="valor-level"><?= htmlspecialchars($nivel_codigo) ?></p>
            </div>

            <div class="card-cronometro">
                <h4>Cronómetro</h4>
                <div id="display-cronometro">00:00</div>
                <div class="controles-cronometro">
                    <button type="button" id="btn-start-stop" class="btn-cronometro btn-start">Start</button>
                    <button type="button" id="btn-reiniciar" class="btn-cronometro btn-reiniciar" title="Pone el cronómetro en 00:00 sin guardar nada">Reset</button>
                </div>
            </div>

            <div class="card-mini">
                <h4>Penalización</h4>
                <p id="valor-penalty-segundos">0 seg</p>
                <span class="subvalor" id="valor-penalty-unidades">(0 penalidades)</span>
            </div>
        </div>

        <!-- ===================== TIEMPOS ===================== -->
        <div class="fila-tiempos">
            <span>Deletreo: <strong id="tiempo-deletreo">00:00</strong></span>
            <?php if ($usa_oracion): ?>
            <span>Oración: <strong id="tiempo-oracion">00:00</strong></span>
            <?php endif; ?>
            <span>Total: <strong id="tiempo-total">00:00</strong></span>
        </div>

        <!-- ===================== ACCIONES ===================== -->
        <div class="fila-acciones">
            <?php if ($usa_oracion): ?>
            <button type="button" id="btn-guardar-deletreo" class="btn-accion btn-guardar-parcial">Guardar tiempo deletreo</button>
            <?php endif; ?>
            <button type="button" id="btn-correcto" class="btn-accion btn-correcto">✓ Correcto</button>
            <button type="button" id="btn-incorrecto" class="btn-accion btn-incorrecto">✗ Incorrecto</button>
        </div>
    </main>

    <!-- ===================== NAVEGACIÓN ENTRE ALUMNOS ===================== -->
    <div class="fila-navegacion-alumnos">
        <a class="nav-alumno nav-anterior <?= $url_anterior ? '' : 'nav-disabled' ?>" href="<?= $url_anterior ?: '#' ?>">
            <?php if ($anterior): ?>
                ← Anterior: <?= htmlspecialchars($anterior['institucion_nombre'] . ' — ' . $anterior['apellido'] . ', ' . $anterior['nombre']) ?>
            <?php else: ?>
                ← Anterior: —
            <?php endif; ?>
        </a>
        <a class="nav-alumno nav-siguiente <?= $url_siguiente ? '' : 'nav-disabled' ?>" href="<?= $url_siguiente ?: '#' ?>">
            <?php if ($siguiente): ?>
                Siguiente: <?= htmlspecialchars($siguiente['institucion_nombre'] . ' — ' . $siguiente['apellido'] . ', ' . $siguiente['nombre']) ?> →
            <?php else: ?>
                Ver resultados del level →
            <?php endif; ?>
        </a>
    </div>

    <p class="ayuda-teclado">Espacio: Start/Stop &nbsp;·&nbsp; Enter: Guardar y avanzar &nbsp;·&nbsp; ←/→: Anterior/Siguiente (casos especiales) &nbsp;·&nbsp; +/-: Penalización</p>

    <footer class="panel-footer-minimo">🐝 Spelling Bee</footer>

    <!-- ===================== OVERLAY DE RESULTADO (aparece unos segundos al guardar) ===================== -->
    <div id="overlay-resultado" class="overlay-resultado oculto">
        <div class="overlay-tarjeta">
            <div class="overlay-icono" id="overlay-icono">✓</div>
            <p class="overlay-nombre" id="overlay-nombre"></p>
            <p class="overlay-tiempo" id="overlay-tiempo"></p>
        </div>
    </div>

    <input type="hidden" id="dato-inscripcion-id" value="<?= $actual['inscripcion_id'] ?? '' ?>">
    <input type="hidden" id="dato-usa-oracion" value="<?= $usa_oracion ? '1' : '0' ?>">
    <input type="hidden" id="dato-competencia-round-id" value="<?= $competencia_round_id ?? '' ?>">
    <input type="hidden" id="dato-palabra-id" value="<?= $palabra_id ?? '' ?>">
    <input type="hidden" id="dato-alumno-nombre" value="<?= $actual ? htmlspecialchars($actual['apellido'] . ', ' . $actual['nombre']) : '' ?>">
    <input type="hidden" id="dato-preexistente-deletreo" value="<?= $resultado_existente['tiempo_deletreo'] ?? '' ?>">
    <input type="hidden" id="dato-preexistente-oracion" value="<?= $resultado_existente['tiempo_oracion'] ?? '' ?>">
    <input type="hidden" id="dato-preexistente-penalty" value="<?= $resultado_existente['penalizacion_segundos'] ?? '0' ?>">

    <script src="assets/js/utils.js"></script>
    <script src="assets/js/panel_control.js"></script>
</body>
</html>
