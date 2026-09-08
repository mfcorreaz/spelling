<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor', 'admin', 'directivo']);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/logica_torneo.php';

$competencia_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM competencias WHERE id = ?");
$stmt->execute([$competencia_id]);
$competencia = $stmt->fetch();
if (!$competencia) { die('El torneo no existe.'); }

$levels = obtener_levels_competencia($pdo, $competencia_id);

$columnas = [];
foreach ($levels as $nivel) {
    $columnas[] = ['codigo' => $nivel['codigo'], 'podio' => obtener_podio_nivel($pdo, $competencia_id, $nivel['id'])];
}

function clase_puesto(int $puesto): string {
    if ($puesto === 1) return 'puesto-oro';
    if ($puesto === 2) return 'puesto-plata';
    if ($puesto === 3) return 'puesto-bronce';
    return 'puesto-resto';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Podio Final - <?= htmlspecialchars($competencia['nombre']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/panel_control.css">
    <link rel="stylesheet" href="assets/css/resultados_nivel.css">
    <link rel="stylesheet" href="assets/css/resumen_ronda.css">
    <link rel="stylesheet" href="assets/css/podio_final.css">
</head>
<body class="panel-kiosko">
    <a href="ver_competencia.php?id=<?= $competencia_id ?>" class="btn-salir-panel">← Salir</a>

    <header class="panel-header">
        <h1 class="nombre-torneo">🏆 Podio Final</h1>
        <p class="subtitulo-round"><?= htmlspecialchars($competencia['nombre']) ?></p>
    </header>

    <main class="grilla-resumen-ronda">
        <?php foreach ($columnas as $col): ?>
            <div class="columna-nivel">
                <div class="columna-nivel-titulo">
                    <span class="titulo-nivel-mini"><?= htmlspecialchars($col['codigo']) ?></span>
                </div>
                <?php if (empty($col['podio'])): ?>
                    <p class="sin-datos-oscuro">Sin resultados.</p>
                <?php else: ?>
                    <ol class="lista-podio">
                        <?php foreach ($col['podio'] as $f): ?>
                            <li class="item-podio <?= clase_puesto($f['puesto_final']) ?>">
                                <span class="puesto-numero"><?= $f['puesto_final'] ?>°</span>
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
        <a href="ver_competencia.php?id=<?= $competencia_id ?>" class="btn-continuar">Volver al torneo</a>
    </div>

    <footer class="panel-footer-minimo">🐝 Spelling Bee</footer>
</body>
</html>
