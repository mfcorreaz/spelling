<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor', 'admin', 'directivo']);
require_once __DIR__ . '/config/conexion.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true);

$modo = $datos['modo'] ?? 'final'; // 'parcial' (solo deletreo) | 'final' (completo)
$inscripcion_id = (int)($datos['inscripcion_id'] ?? 0);
$competencia_round_id = (int)($datos['competencia_round_id'] ?? 0);
$tiempo_deletreo = (float)($datos['tiempo_deletreo'] ?? 0);

if (!$inscripcion_id || !$competencia_round_id || $tiempo_deletreo <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan datos obligatorios (competidor y tiempo).']);
    exit;
}

// Verificar que la inscripción pertenezca a una competencia de este profesor (o staff)
$stmt = $pdo->prepare("
    SELECT c.creado_por_usuario_id
    FROM inscripciones i
    JOIN competencias c ON c.id = i.competencia_id
    WHERE i.id = ?
");
$stmt->execute([$inscripcion_id]);
$fila = $stmt->fetch();

if (!$fila) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Inscripción no encontrada.']);
    exit;
}

$es_dueno = $fila['creado_por_usuario_id'] == $_SESSION['usuario_id'];
$es_staff = in_array($_SESSION['rol'], ['admin', 'directivo'], true);
if (!$es_dueno && !$es_staff) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso.']);
    exit;
}

// ¿Ya existe un resultado para este alumno en este round? (evita duplicar filas)
$stmt = $pdo->prepare("SELECT id FROM resultados WHERE inscripcion_id = ? AND competencia_round_id = ?");
$stmt->execute([$inscripcion_id, $competencia_round_id]);
$existente = $stmt->fetch();

if ($modo === 'parcial') {
    // Guarda (o actualiza) solo el tiempo de deletreo, sin veredicto todavía.
    if ($existente) {
        $stmt = $pdo->prepare("UPDATE resultados SET tiempo_deletreo = ? WHERE id = ?");
        $stmt->execute([$tiempo_deletreo, $existente['id']]);
        $resultado_id = $existente['id'];
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO resultados (inscripcion_id, competencia_round_id, tiempo_deletreo, registrado_por_usuario_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$inscripcion_id, $competencia_round_id, $tiempo_deletreo, $_SESSION['usuario_id']]);
        $resultado_id = $pdo->lastInsertId();
    }
    echo json_encode(['ok' => true, 'resultado_id' => $resultado_id, 'modo' => 'parcial']);
    exit;
}

// ---- Modo FINAL: completa (o crea) el registro con el veredicto ----
$acierto_deletreo = !empty($datos['acierto_deletreo']) ? 1 : 0;
$tiempo_oracion = isset($datos['tiempo_oracion']) && $datos['tiempo_oracion'] !== null ? (float)$datos['tiempo_oracion'] : null;
$oracion_correcta = isset($datos['oracion_correcta']) && $datos['oracion_correcta'] !== null ? (int)$datos['oracion_correcta'] : null;
$penalizacion_segundos = (float)($datos['penalizacion_segundos'] ?? 0);
$tiempo_total = $tiempo_deletreo + ($tiempo_oracion ?? 0) + $penalizacion_segundos;

if ($existente) {
    $stmt = $pdo->prepare("
        UPDATE resultados SET
            tiempo_deletreo = ?, acierto_deletreo = ?,
            tiempo_oracion = ?, oracion_correcta = ?, penalizacion_segundos = ?, tiempo_total = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $tiempo_deletreo, $acierto_deletreo,
        $tiempo_oracion, $oracion_correcta, $penalizacion_segundos, $tiempo_total,
        $existente['id']
    ]);
    $resultado_id = $existente['id'];
} else {
    $stmt = $pdo->prepare("
        INSERT INTO resultados
            (inscripcion_id, competencia_round_id, tiempo_deletreo, acierto_deletreo,
             tiempo_oracion, oracion_correcta, penalizacion_segundos, tiempo_total, registrado_por_usuario_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $inscripcion_id, $competencia_round_id, $tiempo_deletreo, $acierto_deletreo,
        $tiempo_oracion, $oracion_correcta, $penalizacion_segundos, $tiempo_total, $_SESSION['usuario_id']
    ]);
    $resultado_id = $pdo->lastInsertId();
}

echo json_encode(['ok' => true, 'resultado_id' => $resultado_id, 'modo' => 'final']);
