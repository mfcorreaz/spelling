// ============================================================
// PANEL_CONTROL.JS
// Cronómetro + guardado de resultados + atajos de teclado:
//   Enter   = guarda (parcial en deletreo, final en oración) y avanza
//   Espacio = Start/Stop del cronómetro
//   ←/→     = anterior/siguiente (casos especiales, manual)
//   +/-     = suma/resta penalización (cada unidad = 5 segundos)
// El tiempo de deletreo se guarda al toque como "parcial" en la
// base, así si el jurado se va sin querer a otro registro, al
// volver retoma justo donde había quedado.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  const displayCronometro = document.getElementById('display-cronometro');
  const tiempoDeletreoEl = document.getElementById('tiempo-deletreo');
  const tiempoOracionEl = document.getElementById('tiempo-oracion'); // puede no existir
  const tiempoTotalEl = document.getElementById('tiempo-total');
  const valorPenaltySegundos = document.getElementById('valor-penalty-segundos');
  const valorPenaltyUnidades = document.getElementById('valor-penalty-unidades');
  const btnStartStop = document.getElementById('btn-start-stop');

  const overlay = document.getElementById('overlay-resultado');
  const overlayIcono = document.getElementById('overlay-icono');
  const overlayNombre = document.getElementById('overlay-nombre');
  const overlayTiempo = document.getElementById('overlay-tiempo');

  const usaOracion = document.getElementById('dato-usa-oracion').value === '1';
  const inscripcionId = document.getElementById('dato-inscripcion-id').value;
  const competenciaRoundId = document.getElementById('dato-competencia-round-id').value;
  const alumnoNombre = document.getElementById('dato-alumno-nombre').value;

  // ---- Datos de un resultado ya guardado antes (retomar si el jurado volvió) ----
  const preDeletreo = parseFloat(document.getElementById('dato-preexistente-deletreo').value) || 0;
  const preOracion = parseFloat(document.getElementById('dato-preexistente-oracion').value) || 0;
  const prePenalty = parseFloat(document.getElementById('dato-preexistente-penalty').value) || 0;

  let corriendo = false;
  let inicio = 0;
  let acumulado = 0;
  let intervalo = null;

  let msDeletreo = preDeletreo * 1000;
  let msOracion = preOracion * 1000;
  // Si ya había un tiempo de deletreo guardado y el formato usa oración,
  // arrancamos directo en la etapa de oración (ya no hace falta re-tomarlo).
  let etapaActual = (usaOracion && preDeletreo > 0) ? 'oracion' : 'deletreo';
  let penaltyUnidades = Math.round(prePenalty / 5);
  let procesando = false;

  function formatearTiempo(ms) {
    const totalCs = Math.floor(ms / 10);
    const cs = totalCs % 100;
    const totalSeg = Math.floor(totalCs / 100);
    const pad = (n) => String(n).padStart(2, '0');
    if (totalSeg < 60) return `${pad(totalSeg)}:${pad(cs)}`;
    const min = Math.floor(totalSeg / 60);
    const seg = totalSeg % 60;
    return `${pad(min)}:${pad(seg)}:${pad(cs)}`;
  }

  function tiempoActualMs() {
    return acumulado + (corriendo ? Date.now() - inicio : 0);
  }

  function actualizarDisplay() {
    displayCronometro.textContent = formatearTiempo(tiempoActualMs());
  }

  function iniciar() {
    if (corriendo || procesando) return;
    corriendo = true;
    inicio = Date.now();
    intervalo = setInterval(actualizarDisplay, 30);
    btnStartStop.textContent = 'Stop';
    btnStartStop.classList.add('btn-stop');
  }

  function pausar() {
    if (!corriendo) return;
    acumulado += Date.now() - inicio;
    corriendo = false;
    clearInterval(intervalo);
    actualizarDisplay();
    btnStartStop.textContent = 'Start';
    btnStartStop.classList.remove('btn-stop');
  }

  function alternarStartStop() {
    corriendo ? pausar() : iniciar();
  }

  function reiniciar() {
    corriendo = false;
    clearInterval(intervalo);
    acumulado = 0;
    actualizarDisplay();
    btnStartStop.textContent = 'Start';
    btnStartStop.classList.remove('btn-stop');
  }

  function actualizarResumenTiempos() {
    tiempoDeletreoEl.textContent = formatearTiempo(msDeletreo);
    if (tiempoOracionEl) tiempoOracionEl.textContent = formatearTiempo(msOracion);
    const penaltyMs = penaltyUnidades * 5 * 1000;
    tiempoTotalEl.textContent = formatearTiempo(msDeletreo + msOracion + penaltyMs);
  }

  function actualizarPenaltyDisplay() {
    valorPenaltySegundos.textContent = `${penaltyUnidades * 5} seg`;
    valorPenaltyUnidades.textContent = `(${penaltyUnidades} penalidad${penaltyUnidades === 1 ? '' : 'es'})`;
    actualizarResumenTiempos();
  }

  function sumarPenalty() {
    if (procesando) return;
    penaltyUnidades += 1;
    actualizarPenaltyDisplay();
  }

  function restarPenalty() {
    if (procesando) return;
    penaltyUnidades = Math.max(0, penaltyUnidades - 1);
    actualizarPenaltyDisplay();
  }

  btnStartStop.addEventListener('click', alternarStartStop);
  document.getElementById('btn-reiniciar').addEventListener('click', () => { if (!procesando) reiniciar(); });

  // ---- Guarda el tiempo de deletreo como "parcial" en la base y pasa a tomar la oración ----
  async function guardarParcialDeletreo() {
    if (procesando) return false;
    pausar();
    const tiempo = tiempoActualMs();
    if (tiempo <= 0) {
      mostrarMensaje('Iniciá el cronómetro antes de guardar el tiempo de deletreo.', 'error');
      return false;
    }
    msDeletreo = tiempo;
    actualizarResumenTiempos();

    if (inscripcionId && competenciaRoundId) {
      try {
        await peticion('guardar_resultado.php', {
          method: 'POST',
          body: JSON.stringify({
            modo: 'parcial',
            inscripcion_id: inscripcionId,
            competencia_round_id: competenciaRoundId,
            tiempo_deletreo: (msDeletreo / 1000).toFixed(2),
          }),
        });
      } catch (err) {
        mostrarMensaje('No se pudo guardar el tiempo de deletreo (sin conexión).', 'error');
      }
    }

    etapaActual = 'oracion';
    reiniciar();
    actualizarResumenTiempos();
    return true;
  }

  const btnGuardarParcial = document.getElementById('btn-guardar-deletreo');
  if (btnGuardarParcial && etapaActual === 'oracion') {
    btnGuardarParcial.style.display = 'none';
    mostrarMensaje('Se retomó el tiempo de deletreo ya guardado. Tomá la oración.', 'exito');
  }
  if (btnGuardarParcial && etapaActual === 'deletreo') {
    btnGuardarParcial.addEventListener('click', async () => {
      const ok = await guardarParcialDeletreo();
      if (ok) mostrarMensaje('Tiempo de deletreo guardado. Arrancá el cronómetro para la oración.', 'exito');
    });
  }

  // ---- Guardar resultado final (Correcto / Incorrecto) ----
  async function finalizar(acierto) {
    if (procesando) return;
    pausar();

    if (usaOracion) {
      if (etapaActual !== 'oracion') {
        mostrarMensaje('Primero guardá el tiempo de deletreo antes de continuar.', 'error');
        return;
      }
      const tiempo = tiempoActualMs();
      if (tiempo > 0) msOracion = tiempo;
    } else {
      const tiempo = tiempoActualMs();
      if (tiempo > 0) msDeletreo = tiempo;
    }
    actualizarResumenTiempos();

    if (msDeletreo <= 0) {
      mostrarMensaje('Falta cargar el tiempo de deletreo.', 'error');
      return;
    }
    if (usaOracion && msOracion <= 0) {
      mostrarMensaje('Falta cargar el tiempo de oración.', 'error');
      return;
    }
    if (!inscripcionId || !competenciaRoundId) {
      mostrarMensaje('Faltan datos para guardar (¿ya configuraste los rounds del torneo?).', 'error');
      return;
    }

    procesando = true;

    const payload = {
      modo: 'final',
      inscripcion_id: inscripcionId,
      competencia_round_id: competenciaRoundId,
      tiempo_deletreo: (msDeletreo / 1000).toFixed(2),
      acierto_deletreo: acierto ? 1 : 0,
      tiempo_oracion: usaOracion ? (msOracion / 1000).toFixed(2) : null,
      oracion_correcta: usaOracion ? (acierto ? 1 : 0) : null,
      penalizacion_segundos: penaltyUnidades * 5,
    };

    try {
      const resp = await peticion('guardar_resultado.php', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      if (resp.ok) {
        const tiempoTotalSeg = (msDeletreo / 1000) + (usaOracion ? msOracion / 1000 : 0) + penaltyUnidades * 5;
        mostrarOverlayResultado(acierto, tiempoTotalSeg.toFixed(2));
      } else {
        procesando = false;
        mostrarMensaje('No se pudo guardar: ' + (resp.error || ''), 'error');
      }
    } catch (err) {
      procesando = false;
      mostrarMensaje('Error de conexión al guardar el resultado.', 'error');
    }
  }

  function mostrarOverlayResultado(acierto, tiempoTotalTexto) {
    overlayIcono.textContent = acierto ? '✓' : '✗';
    overlayIcono.className = 'overlay-icono ' + (acierto ? 'overlay-correcto' : 'overlay-incorrecto');
    overlayNombre.textContent = alumnoNombre || 'Alumno';
    overlayTiempo.textContent = `Tiempo total: ${tiempoTotalTexto}s`;
    overlay.classList.add('visible');
    setTimeout(() => irASiguiente(), 2500);
  }

  document.getElementById('btn-correcto').addEventListener('click', () => finalizar(true));
  document.getElementById('btn-incorrecto').addEventListener('click', () => finalizar(false));

  function irASiguiente() {
    const link = document.querySelector('.nav-siguiente');
    if (link && !link.classList.contains('nav-disabled')) window.location.href = link.href;
  }
  function irAAnterior() {
    if (procesando) return;
    const link = document.querySelector('.nav-anterior');
    if (link && !link.classList.contains('nav-disabled')) window.location.href = link.href;
  }

  // ---- Atajos de teclado ----
  document.addEventListener('keydown', async (e) => {
    if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
    if (procesando && e.key !== 'Enter') return;

    switch (e.key) {
      case 'Enter':
        e.preventDefault();
        if (usaOracion && etapaActual === 'deletreo') {
          const ok = await guardarParcialDeletreo();
          if (ok) mostrarMensaje('Tiempo de deletreo guardado. Arrancá para tomar la oración.', 'exito');
        } else {
          finalizar(true);
        }
        break;
      case ' ':
        e.preventDefault();
        alternarStartStop();
        break;
      case 'ArrowRight':
        e.preventDefault();
        irASiguiente();
        break;
      case 'ArrowLeft':
        e.preventDefault();
        irAAnterior();
        break;
      case '+':
      case '=':
        e.preventDefault();
        sumarPenalty();
        break;
      case '-':
        e.preventDefault();
        restarPenalty();
        break;
    }
  });

  actualizarDisplay();
  actualizarResumenTiempos();
  actualizarPenaltyDisplay();
});
