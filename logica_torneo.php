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

function obtener_max_round(PDO $pdo, int $competencia_id, ?int $nivel_id = null): int {
    if ($nivel_id !== null) {
        $stmt = $pdo->prepare("SELECT MAX(numero_round) AS m FROM competencia_rounds WHERE competencia_id = ? AND nivel_id = ?");
        $stmt->execute([$competencia_id, $nivel_id]);
    } else {
        // Sin nivel_id: máximo global (usado solo para saber si YA TERMINARON todos los niveles)
        $stmt = $pdo->prepare("SELECT MAX(numero_round) AS m FROM competencia_rounds WHERE competencia_id = ?");
        $stmt->execute([$competencia_id]);
    }
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
 * Si no se configuró cantidad_pasan (o es inválida), se calcula un valor por
 * defecto: se elimina 1/N de los participantes en cada ronda, donde N es la
 * cantidad total de rounds del torneo (ej: 3 rounds → se elimina 1/3 por ronda).
 */
function obtener_resultados_de_ronda(PDO $pdo, int $competencia_id, int $nivel_id, int $numero_round): array {
    $stmt = $pdo->prepare("SELECT id, cantidad_pasan FROM competencia_rounds WHERE competencia_id = ? AND nivel_id = ? AND numero_round = ?");
    $stmt->execute([$competencia_id, $nivel_id, $numero_round]);
    $round_row = $stmt->fetch();

    if (!$round_row) {
        return ['competencia_round_id' => null, 'cantidad_pasan' => 1, 'filas' => []];
    }

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

    $total_participantes = count($filas);
    $cantidad_pasan = $round_row['cantidad_pasan'];

    // Si no se configuró (o quedó mal cargado: 0, negativo, o mayor a la cantidad real
    // de participantes), se calcula un valor por defecto eliminando 1/N por ronda.
    if (empty($cantidad_pasan) || $cantidad_pasan <= 0 || $cantidad_pasan > $total_participantes) {
        $max_round_nivel = obtener_max_round($pdo, $competencia_id, $nivel_id);
        if ($numero_round >= $max_round_nivel) {
            $cantidad_pasan = 1; // ronda final de ESTE nivel: siempre queda 1 ganador
        } else {
            $eliminar = intdiv($total_participantes, $max_round_nivel);
            $cantidad_pasan = max(1, $total_participantes - $eliminar);
        }
    }

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
 * Reconstruye el ranking final completo de un nivel: arranca por el
 * ganador de la última ronda, sigue con quienes fueron quedando en el
 * camino (ordenados por en qué ronda salieron, y por tiempo dentro de
 * esa ronda). Puesto 1 = campeón, puesto 2 = segundo puesto, etc.
 */
function obtener_podio_nivel(PDO $pdo, int $competencia_id, int $nivel_id): array {
    $max_round = obtener_max_round($pdo, $competencia_id, $nivel_id);
    $ranking = [];

    for ($r = $max_round; $r >= 1; $r--) {
        $datos = obtener_resultados_de_ronda($pdo, $competencia_id, $nivel_id, $r);
        if (empty($datos['filas'])) continue;

        if ($r === $max_round) {
            // Ronda final: primero el/los que pasaron (el campeón), después el resto de esa ronda
            foreach ($datos['filas'] as $f) { if ($f['pasa']) $ranking[] = $f; }
            foreach ($datos['filas'] as $f) { if (!$f['pasa']) $ranking[] = $f; }
        } else {
            // Rondas anteriores: solo los que quedaron eliminados EN esa ronda
            // (los que pasaron ya están cargados desde una ronda posterior)
            foreach ($datos['filas'] as $f) { if (!$f['pasa']) $ranking[] = $f; }
        }
    }

    foreach ($ranking as $i => &$fila) {
        $fila['puesto_final'] = $i + 1;
    }

    return $ranking;
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
