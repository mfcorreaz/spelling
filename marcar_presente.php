<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor', 'admin', 'directivo']);
require_once __DIR__ . '/config/conexion.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true);
$inscripcion_id = (int)($datos['inscripcion_id'] ?? 0);
$presente = !empty($datos['presente']) ? 1 : 0;

if (!$inscripcion_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta inscripcion_id']);
    exit;
}

// Verificar que la inscripción pertenezca a una competencia de este profesor (o que sea admin/directivo)
$stmt = $pdo->prepare("
    SELECT i.id, c.creado_por_usuario_id, c.estado
    FROM inscripciones i
    JOIN competencias c ON c.id = i.competencia_id
    WHERE i.id = ?
");
$stmt->execute([$inscripcion_id]);
$fila = $stmt->fetch();

if (!$fila) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Inscripción no encontrada']);
    exit;
}

if ($fila['estado'] === 'finalizada') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Este torneo ya finalizó, no se puede modificar la asistencia.']);
    exit;
}

$es_dueno = $fila['creado_por_usuario_id'] == $_SESSION['usuario_id'];
$es_staff = in_array($_SESSION['rol'], ['admin', 'directivo'], true);
if (!$es_dueno && !$es_staff) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

$stmt = $pdo->prepare("UPDATE inscripciones SET presente = ? WHERE id = ?");
$stmt->execute([$presente, $inscripcion_id]);

echo json_encode(['ok' => true, 'presente' => $presente]);
