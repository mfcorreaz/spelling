<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor']);
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: crear_competencia.php');
    exit;
}

// ---- Datos básicos ----
$nombre = trim($_POST['nombre'] ?? '');
$fecha = $_POST['fecha'] ?? '';
$tipo_alcance = ($_POST['tipo_alcance'] ?? 'institucional') === 'interescolar' ? 'interescolar' : 'institucional';
$formato = $_POST['formato'] ?? 'deletreo';
$usa_audio = isset($_POST['usa_audio']) ? 1 : 0;
$tipo_desempate = $_POST['tipo_desempate'] ?? 'tiempo_acumulado';
$institucion_organizadora_id = !empty($_POST['institucion_organizadora_id']) ? (int)$_POST['institucion_organizadora_id'] : null;

if (!$institucion_organizadora_id) {
    die('Falta elegir la institución del torneo. Volvé atrás e intentá de nuevo.');
}

// ---- Levels elegidos ----
$levels = $_POST['levels'] ?? [];      // array de nivel_id
$rounds = $_POST['rounds'] ?? [];      // rounds[nivel_id][numero_round][pasan]
$alumnos_ids = $_POST['alumnos'] ?? [];

if ($nombre === '' || $fecha === '' || empty($levels)) {
    die('Faltan datos obligatorios (nombre, fecha o levels). Volvé atrás e intentá de nuevo.');
}

try {
    $pdo->beginTransaction();

    // 1) Crear la competencia
    $stmt = $pdo->prepare("
        INSERT INTO competencias
            (nombre, fecha, tipo, tipo_alcance, formato, usa_audio, tipo_desempate, estado, es_publica, institucion_organizadora_id, creado_por_usuario_id)
        VALUES (?, ?, 'completa', ?, ?, ?, ?, 'borrador', 1, ?, ?)
    ");
    $stmt->execute([
        $nombre, $fecha, $tipo_alcance, $formato, $usa_audio, $tipo_desempate,
        $institucion_organizadora_id, $_SESSION['usuario_id']
    ]);
    $competencia_id = $pdo->lastInsertId();

    // 2) Guardar los levels de la competencia
    $stmtLevel = $pdo->prepare("INSERT INTO competencia_levels (competencia_id, nivel_id) VALUES (?, ?)");
    foreach ($levels as $nivel_id) {
        $stmtLevel->execute([$competencia_id, (int)$nivel_id]);
    }

    // 3) Guardar la config de rounds por nivel
    $stmtRound = $pdo->prepare("
        INSERT INTO competencia_rounds (competencia_id, nivel_id, numero_round, cantidad_pasan)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($rounds as $nivel_id => $rondas) {
        foreach ($rondas as $numero_round => $datos) {
            $cantidad_pasan = !empty($datos['pasan']) ? (int)$datos['pasan'] : null;
            $stmtRound->execute([$competencia_id, (int)$nivel_id, (int)$numero_round, $cantidad_pasan]);
        }
    }

    // 4) Inscribir a los alumnos elegidos (heredando institución, profesor y nivel)
    //    Si el torneo es institucional, se valida también que el alumno pertenezca
    //    a la institución elegida (defensa extra, además del filtro ya aplicado en el JS)
    if (!empty($alumnos_ids)) {
        $stmtAlumno = $pdo->prepare("
            SELECT a.id, a.institucion_id, a.profesor_id, n.id AS nivel_id
            FROM alumnos a
            JOIN niveles n ON a.anio BETWEEN n.anio_desde AND n.anio_hasta
            WHERE a.id = ? AND a.profesor_id = ?
        ");
        $stmtInscribir = $pdo->prepare("
            INSERT INTO inscripciones (competencia_id, alumno_id, nivel_id, institucion_id, profesor_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($alumnos_ids as $alumno_id) {
            $stmtAlumno->execute([(int)$alumno_id, $_SESSION['usuario_id']]);
            $al = $stmtAlumno->fetch();
            if (!$al) {
                continue;
            }
            if ($tipo_alcance === 'institucional' && (int)$al['institucion_id'] !== $institucion_organizadora_id) {
                continue; // se ignora silenciosamente: no pertenece a la institución del torneo
            }
            $stmtInscribir->execute([
                $competencia_id, $al['id'], $al['nivel_id'], $al['institucion_id'], $al['profesor_id']
            ]);
        }
    }

    $pdo->commit();
    header('Location: dashboard_profesor.php?torneo_creado=1');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die('Ocurrió un error al crear el torneo: ' . $e->getMessage());
}
