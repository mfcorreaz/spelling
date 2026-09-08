<?php
// ============================================================
// LOGICA_TORNEO.PHP — funciones reutilizables para el manejo
// de rounds, resultados y avance de eliminación.
// ============================================================

function obtener_levels_competencia(PDO $pdo, int $competencia_id): array {
    $stmt = $pdo->prepare("
        SELECT n.id, n.codigo FROM competencia_levels cl
        JOIN niveles n ON n.id = cl.nivel_id
        WHERE cl.competencia_id = ? ORDER BY n.codigo
    ");
    $stmt->execute([$competencia_id]);
    return $stmt->fetchAll();
}

function obtener_max_round(PDO $pdo, int $competencia_id): int {
    $stmt = $pdo->prepare("SELECT MAX(numero_round) AS m FROM competencia_rounds WHERE competencia_id = ?");
    $stmt->execute([$competencia_id]);
    return (int)($stmt->fetch()['m'] ?? 1);
}

function obtener_competencia_round_id(PDO $pdo, int $competencia_id, int $nivel_id, int $numero_round): ?int {
    $stmt = $pdo->prepare("SELECT id, cantidad_pasan FROM competencia_rounds WHERE competencia_id = ? AND nivel_id = ? AND numero_round = ?");
    $stmt->execute([$competencia_id, $nivel_id, $numero_round]);
    $row = $stmt->fetch();
    return $row['id'] ?? null;
}

/**
 * Devuelve los resultados de un level+round ordenados por tiempo_total ascendente,
 * marcando con 'pasa' = true/false según la cantidad configurada para ese round.
 */
function obtener_resultados_de_ronda(PDO $pdo, int $competencia_id, int $nivel_id, int $numero_round): array {
    $stmt = $pdo->prepare("SELECT id, cantidad_pasan FROM competencia_rounds WHERE competencia_id = ? AND nivel_id = ? AND numero_round = ?");
    $stmt->execute([$competencia_id, $nivel_id, $numero_round]);
    $round_row = $stmt->fetch();

    if (!$round_row) {
        return ['competencia_round_id' => null, 'cantidad_pasan' => 1, 'filas' => []];
    }

    $cantidad_pasan = $round_row['cantidad_pasan'] ?? 1; // si quedó vacío (ronda final), pasa 1 = el ganador

    $stmt = $pdo->prepare("
        SELECT r.id AS resultado_id, r.inscripcion_id, r.tiempo_deletreo, r.tiempo_oracion,
               r.oracion_correcta, r.acierto_deletreo, r.penalizacion_segundos, r.tiempo_total,
               a.nombre, a.apellido, inst.nombre AS institucion_nombre
        FROM resultados r
        JOIN inscripciones i ON i.id = r.inscripcion_id
        JOIN alumnos a ON a.id = i.alumno_id
        JOIN instituciones inst ON inst.id = i.institucion_id
        WHERE r.competencia_round_id = ?
        ORDER BY r.tiempo_total ASC
    ");
    $stmt->execute([$round_row['id']]);
    $filas = $stmt->fetchAll();

    foreach ($filas as $idx => &$fila) {
        $fila['puesto'] = $idx + 1;
        $fila['pasa'] = ($idx < $cantidad_pasan) && $fila['acierto_deletreo']
                        && ($fila['oracion_correcta'] === null || $fila['oracion_correcta']);
    }

    return [
        'competencia_round_id' => $round_row['id'],
        'cantidad_pasan' => $cantidad_pasan,
        'filas' => $filas,
    ];
}

/**
 * Marca como eliminados (en 'inscripciones') a los que no pasaron esta ronda.
 * Se puede llamar varias veces sin problema (idempotente).
 */
function aplicar_eliminacion(PDO $pdo, array $filas): void {
    $stmtElimina = $pdo->prepare("UPDATE inscripciones SET eliminado = 1 WHERE id = ?");
    $stmtAvanza  = $pdo->prepare("UPDATE inscripciones SET eliminado = 0 WHERE id = ?");
    foreach ($filas as $fila) {
        if ($fila['pasa']) {
            $stmtAvanza->execute([$fila['inscripcion_id']]);
        } else {
            $stmtElimina->execute([$fila['inscripcion_id']]);
        }
    }
}
