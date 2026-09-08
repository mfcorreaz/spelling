// ============================================================
// UTILS.JS — funciones JS compartidas por todas las vistas
// ============================================================

/**
 * Muestra un mensaje temporal tipo "toast" (a usarse en formularios AJAX)
 */
function mostrarMensaje(texto, tipo = 'exito') {
  const div = document.createElement('div');
  div.className = tipo === 'error' ? 'alerta-error' : 'alerta-exito';
  div.textContent = texto;
  document.body.prepend(div);
  setTimeout(() => div.remove(), 4000);
}

/**
 * Helper simple para hacer peticiones fetch con JSON
 */
async function peticion(url, opciones = {}) {
  const respuesta = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...opciones,
  });
  return respuesta.json();
}
