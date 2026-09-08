// ============================================================
// RESUMEN_RONDA.JS
// ArrowRight = comenzar la siguiente ronda (si el torneo NO
//              terminó todavía; si ya terminó, no hace nada)
// ArrowLeft  = volver (historial del navegador)
// ============================================================

document.addEventListener('keydown', (e) => {
  if (e.key === 'ArrowRight') {
    e.preventDefault();
    const btn = document.getElementById('btn-siguiente-ronda'); // no existe si el torneo ya finalizó
    if (btn) window.location.href = btn.href;
    // si no existe (torneo finalizado), no hace nada: se queda en esta pantalla
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault();
    window.history.back();
  }
});
