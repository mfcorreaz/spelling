<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor', 'admin', 'directivo']);
require_once __DIR__ . '/config/conexion.php';

$competencia_id = (int)($_GET['id'] ?? 0);

// Traer la competencia (y validar que este profesor sea el dueño, salvo que sea admin/directivo)
$stmt = $pdo->prepare("
    SELECT c.*, i.nombre AS institucion_nombre, u.nombre AS creador_nombre, u.apellido AS creador_apellido
    FROM competencias c
    LEFT JOIN instituciones i ON i.id = c.institucion_organizadora_id
    LEFT JOIN usuarios u ON u.id = c.creado_por_usuario_id
    WHERE c.id = ?
");
$stmt->execute([$competencia_id]);
$competencia = $stmt->fetch();

if (!$competencia) {
    die('El torneo que buscás no existe.');
}

$es_dueno = $competencia['creado_por_usuario_id'] == $_SESSION['usuario_id'];
$es_staff = in_array($_SESSION['rol'], ['admin', 'directivo'], true);
if (!$es_dueno && !$es_staff) {
    http_response_code(403);
    die('No tenés permiso para ver este torneo.');
}

// Levels configurados
$stmt = $pdo->prepare("
    SELECT n.codigo, n.nombre
    FROM competencia_levels cl
    JOIN niveles n ON n.id = cl.nivel_id
    WHERE cl.competencia_id = ?
    ORDER BY n.codigo
");
$stmt->execute([$competencia_id]);
$levels = $stmt->fetchAll();

// Lista de participantes (institución, alumno, nivel, presente)
$stmt = $pdo->prepare("
    SELECT i.id AS inscripcion_id, i.presente,
           a.nombre, a.apellido, a.anio,
           n.codigo AS nivel_codigo,
           inst.nombre AS institucion_nombre
    FROM inscripciones i
    JOIN alumnos a ON a.id = i.alumno_id
    JOIN niveles n ON n.id = i.nivel_id
    JOIN instituciones inst ON inst.id = i.institucion_id
    WHERE i.competencia_id = ?
    ORDER BY inst.nombre, n.codigo, a.apellido, a.nombre
");
$stmt->execute([$competencia_id]);
$participantes = $stmt->fetchAll();

$total = count($participantes);
$presentes = count(array_filter($participantes, fn($p) => $p['presente']));
$ausentes = $total - $presentes;

$etiquetas_estado = [
    'borrador'   => ['texto' => 'Programado', 'clase' => 'estado-verde'],
    'en_curso'   => ['texto' => 'En curso',   'clase' => 'estado-amarillo'],
    'finalizada' => ['texto' => 'Finalizado', 'clase' => 'estado-rojo'],
];
$estado = $etiquetas_estado[$competencia['estado']];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($competencia['nombre']) ?> - Spelling Bee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/ver_competencia.css">
</head>
<body>
    <?php include __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="contenido">

        <!-- ===================== ENCABEZADO DEL TORNEO ===================== -->
        <section class="encabezado-torneo">
            <div>
                <span class="badge-estado <?= $estado['clase'] ?>"><?= $estado['texto'] ?></span>
                <h2><?= htmlspecialchars($competencia['nombre']) ?></h2>
                <p class="detalle-encabezado">
                    📅 <?= date('d/m/Y', strtotime($competencia['fecha'])) ?>
                    &nbsp;|&nbsp; 🏫 <?= htmlspecialchars($competencia['institucion_nombre'] ?? '—') ?>
                    &nbsp;|&nbsp; <?= $competencia['tipo_alcance'] === 'interescolar' ? 'Interescolar' : 'Institucional' ?>
                    &nbsp;|&nbsp; 🎤 <?= $competencia['formato'] === 'deletreo_oracion' ? 'Deletreo + Oración' : 'Solo deletreo' ?>
                    <?php if ($competencia['usa_audio']): ?> &nbsp;|&nbsp; 🎙️ Con audio<?php endif; ?>
                </p>
                <p class="detalle-encabezado">Levels: <?php foreach ($levels as $l): ?><span class="chip-level"><?= $l['codigo'] ?></span><?php endforeach; ?></p>
            </div>
        </section>

        <!-- ===================== INGRESAR AL TORNEO (panel de control) ===================== -->
        <section class="modulo-ingresar">
            <a href="panel_control.php?id=<?= $competencia_id ?>" class="btn btn-ingresar">
                ▶ Ingresar al torneo
            </a>
        </section>

        <!-- ===================== BOTONES DE ACCIÓN / NAVEGACIÓN ===================== -->
        <nav class="tabs-torneo">
            <button class="tab-btn activo" data-tab="asistencia">Lista de asistencia</button>
            <button class="tab-btn" data-tab="rounds" disabled title="Próximamente">Rounds y resultados</button>
            <button class="tab-btn" data-tab="palabras" disabled title="Próximamente">Palabras del torneo</button>
            <button class="tab-btn" data-tab="accesos" disabled title="Próximamente">Códigos de acceso</button>
        </nav>

        <!-- ===================== TAB: LISTA DE ASISTENCIA ===================== -->
        <section class="tab-contenido" data-tab-contenido="asistencia">

            <div class="resumen-asistencia">
                <div class="stat-asistencia"><span class="stat-numero"><?= $total ?></span><span class="stat-label">Inscriptos</span></div>
                <div class="stat-asistencia stat-verde"><span class="stat-numero" id="contador-presentes"><?= $presentes ?></span><span class="stat-label">Presentes</span></div>
                <div class="stat-asistencia stat-rojo"><span class="stat-numero" id="contador-ausentes"><?= $ausentes ?></span><span class="stat-label">Ausentes</span></div>
            </div>

            <?php if (empty($participantes)): ?>
                <p class="sin-datos">Todavía no hay alumnos inscriptos en este torneo.</p>
            <?php else: ?>
                <table class="tabla-asistencia">
                    <thead>
                        <tr>
                            <th>Institución</th>
                            <th>Alumno</th>
                            <th>Nivel</th>
                            <th>Año</th>
                            <th>Presente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participantes as $p): ?>
                            <tr class="fila-participante <?= $p['presente'] ? 'fila-presente' : 'fila-ausente' ?>" data-inscripcion-id="<?= $p['inscripcion_id'] ?>">
                                <td><?= htmlspecialchars($p['institucion_nombre']) ?></td>
                                <td><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) ?></td>
                                <td><span class="chip-level"><?= $p['nivel_codigo'] ?></span></td>
                                <td><?= (int)$p['anio'] ?>°</td>
                                <td>
                                    <label class="switch-presente">
                                        <input type="checkbox" class="chk-presente" <?= $p['presente'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </div>

    <?php include __DIR__ . '/includes/partials/footer.php'; ?>

    <script src="assets/js/utils.js"></script>
    <script src="assets/js/ver_competencia.js"></script>
</body>
</html>
