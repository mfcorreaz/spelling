<?php
require_once __DIR__ . '/includes/verificar_sesion.php';
verificar_rol(['profesor']);
require_once __DIR__ . '/config/conexion.php';

// Niveles disponibles en el sistema (L1, L2, L3)
$niveles = $pdo->query("SELECT * FROM niveles ORDER BY id")->fetchAll();

// Instituciones donde trabaja este profesor (para elegir "institución organizadora")
$stmt = $pdo->prepare("
    SELECT i.id, i.nombre
    FROM instituciones i
    JOIN profesor_institucion pi ON pi.institucion_id = i.id
    WHERE pi.usuario_id = ?
    ORDER BY i.nombre
");
$stmt->execute([$_SESSION['usuario_id']]);
$instituciones = $stmt->fetchAll();

// Alumnos a cargo de este profesor, con su nivel ya calculado
$stmt = $pdo->prepare("
    SELECT a.id, a.nombre, a.apellido, a.anio, n.codigo AS nivel_codigo, a.institucion_id, i.nombre AS institucion_nombre
    FROM alumnos a
    JOIN niveles n ON a.anio BETWEEN n.anio_desde AND n.anio_hasta
    JOIN instituciones i ON i.id = a.institucion_id
    WHERE a.profesor_id = ?
    ORDER BY n.codigo, a.apellido, a.nombre
");
$stmt->execute([$_SESSION['usuario_id']]);
$alumnos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Torneo - Spelling Bee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/crear_competencia.css">
</head>
<body>
    <?php include __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="contenido">
        <h2>Crear nuevo torneo</h2>

        <!-- Indicador de pasos -->
        <div class="pasos-indicador">
            <span class="paso-item activo" data-paso-nav="1">1. Datos</span>
            <span class="paso-item" data-paso-nav="2">2. Levels y Rounds</span>
            <span class="paso-item" data-paso-nav="3">3. Alumnos</span>
            <span class="paso-item" data-paso-nav="4">4. Confirmar</span>
        </div>

        <form id="form-crear-torneo" action="procesar_crear_competencia.php" method="POST">

            <!-- ===================== PASO 1: DATOS BÁSICOS ===================== -->
            <div class="paso" data-paso="1">
                <div class="campo">
                    <label for="nombre">Nombre del torneo</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Ej: Spelling Bee Interescolar 2026">
                </div>

                <div class="campo">
                    <label for="fecha">Fecha</label>
                    <input type="date" id="fecha" name="fecha" required>
                </div>

                <div class="campo">
                    <label>Formato</label>
                    <div class="opciones-radio">
                        <label><input type="radio" name="formato" value="deletreo" checked> Solo deletreo</label>
                        <label><input type="radio" name="formato" value="deletreo_oracion"> Deletreo + Oración</label>
                    </div>
                </div>

                <div class="campo">
                    <label><input type="checkbox" name="usa_audio" value="1"> Grabar audio de las performances</label>
                </div>

                <div class="campo">
                    <label for="tipo_desempate">Método de desempate</label>
                    <select id="tipo_desempate" name="tipo_desempate">
                        <option value="tiempo_acumulado">Tiempo acumulado (suma de todos los rounds)</option>
                        <option value="ultimo_round">Menor tiempo en el último round</option>
                        <option value="round_extra">Round extra en vivo</option>
                        <option value="menor_penalizacion">Menor cantidad de penalizaciones</option>
                    </select>
                </div>

                <div class="campo">
                    <label>Alcance del torneo</label>
                    <div class="opciones-radio">
                        <label><input type="radio" name="tipo_alcance" value="institucional" class="radio-alcance" checked> Institucional (solo alumnos de una escuela)</label>
                        <label><input type="radio" name="tipo_alcance" value="interescolar" class="radio-alcance"> Interescolar (alumnos de varias escuelas)</label>
                    </div>
                </div>

                <div class="campo">
                    <label for="institucion_organizadora_id" id="label-institucion">Institución del torneo</label>
                    <select id="institucion_organizadora_id" name="institucion_organizadora_id" required>
                        <option value="">Elegí una institución</option>
                        <?php foreach ($instituciones as $inst): ?>
                            <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="acciones-paso">
                    <button type="button" class="btn btn-siguiente">Siguiente →</button>
                </div>
            </div>

            <!-- ===================== PASO 2: LEVELS Y ROUNDS ===================== -->
            <div class="paso" data-paso="2" style="display:none;">
                <p class="ayuda-paso">Elegí qué levels van a participar. Por cada level elegido, configurá cuántos rounds tiene y cuántos alumnos pasan a la siguiente ronda.</p>

                <div class="campo">
                    <label>Levels que participan</label>
                    <div class="opciones-checkbox">
                        <?php foreach ($niveles as $n): ?>
                            <label>
                                <input type="checkbox" class="chk-nivel" name="levels[]" value="<?= $n['id'] ?>" data-codigo="<?= $n['codigo'] ?>">
                                <?= htmlspecialchars($n['codigo'] . ' — ' . $n['nombre']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="config-rounds-por-nivel"></div>

                <div class="acciones-paso">
                    <button type="button" class="btn btn-secundario btn-anterior">← Atrás</button>
                    <button type="button" class="btn btn-siguiente">Siguiente →</button>
                </div>
            </div>

            <!-- ===================== PASO 3: ALUMNOS ===================== -->
            <div class="paso" data-paso="3" style="display:none;">
                <p class="ayuda-paso">Marcá los alumnos que participan. Solo se muestran los de los levels que elegiste en el paso anterior.</p>

                <?php if (empty($alumnos)): ?>
                    <p class="sin-datos">Todavía no tenés alumnos cargados a tu cargo.</p>
                <?php else: ?>
                    <?php
                    $alumnos_por_nivel = [];
                    foreach ($alumnos as $al) {
                        $alumnos_por_nivel[$al['nivel_codigo']][] = $al;
                    }
                    ?>
                    <?php foreach ($alumnos_por_nivel as $codigo => $lista): ?>
                        <div class="grupo-alumnos-nivel" data-nivel-codigo="<?= $codigo ?>" style="display:none;">
                            <h4><?= $codigo ?></h4>
                            <?php foreach ($lista as $al): ?>
                                <label class="fila-alumno" data-institucion-id="<?= $al['institucion_id'] ?>">
                                    <input type="checkbox" name="alumnos[]" value="<?= $al['id'] ?>">
                                    <?= htmlspecialchars($al['apellido'] . ', ' . $al['nombre']) ?>
                                    <span class="detalle-alumno"><?= (int)$al['anio'] ?>° año — <?= htmlspecialchars($al['institucion_nombre']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="acciones-paso">
                    <button type="button" class="btn btn-secundario btn-anterior">← Atrás</button>
                    <button type="button" class="btn btn-siguiente">Siguiente →</button>
                </div>
            </div>

            <!-- ===================== PASO 4: CONFIRMAR ===================== -->
            <div class="paso" data-paso="4" style="display:none;">
                <h3>Revisá antes de crear el torneo</h3>
                <div id="resumen-confirmacion" class="resumen-caja"></div>

                <div class="acciones-paso">
                    <button type="button" class="btn btn-secundario btn-anterior">← Atrás</button>
                    <button type="submit" class="btn btn-confirmar">✓ Crear torneo</button>
                </div>
            </div>

        </form>
    </div>

    <?php include __DIR__ . '/includes/partials/footer.php'; ?>

    <script src="assets/js/utils.js"></script>
    <script src="assets/js/crear_competencia.js"></script>
</body>
</html>
