// ============================================================
// RESULTADOS_NIVEL.JS
// ArrowRight = continuar (siguiente level o resumen de ronda)
// ArrowLeft  = volver (historial del navegador)
// ============================================================

document.addEventListener('keydown', (e) => {
  if (e.key === 'ArrowRight') {
    e.preventDefault();
    const btn = document.getElementById('btn-continuar');
    if (btn) window.location.href = btn.href;
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault();
    window.history.back();
  }
});
