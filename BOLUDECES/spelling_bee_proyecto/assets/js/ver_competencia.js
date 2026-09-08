// ============================================================
// VER_COMPETENCIA.JS
// Maneja el marcado de presente/ausente sin recargar la página
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  const contadorPresentes = document.getElementById('contador-presentes');
  const contadorAusentes = document.getElementById('contador-ausentes');

  document.querySelectorAll('.chk-presente').forEach(chk => {
    chk.addEventListener('change', async (e) => {
      const fila = e.target.closest('.fila-participante');
      const inscripcionId = fila.dataset.inscripcionId;
      const presente = e.target.checked;

      try {
        const resp = await peticion('marcar_presente.php', {
          method: 'POST',
          body: JSON.stringify({ inscripcion_id: inscripcionId, presente }),
        });

        if (resp.ok) {
          fila.classList.toggle('fila-presente', presente);
          fila.classList.toggle('fila-ausente', !presente);
          actualizarContadores();
        } else {
          mostrarMensaje('No se pudo actualizar: ' + (resp.error || ''), 'error');
          e.target.checked = !presente; // revertir visualmente
        }
      } catch (err) {
        mostrarMensaje('Error de conexión al marcar asistencia.', 'error');
        e.target.checked = !presente;
      }
    });
  });

  function actualizarContadores() {
    const total = document.querySelectorAll('.chk-presente').length;
    const presentes = document.querySelectorAll('.chk-presente:checked').length;
    contadorPresentes.textContent = presentes;
    contadorAusentes.textContent = total - presentes;
  }

  // ---- Tabs (por ahora solo "asistencia" está activo) ----
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));
      btn.classList.add('activo');
      document.querySelectorAll('.tab-contenido').forEach(sec => {
        sec.style.display = (sec.dataset.tabContenido === btn.dataset.tab) ? 'block' : 'none';
      });
    });
  });
});
