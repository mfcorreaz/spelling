// ============================================================
// CREAR_COMPETENCIA.JS
// Maneja: navegación del wizard, generación dinámica de la
// config de rounds por nivel, filtro de alumnos por nivel
// elegido, y el resumen final antes de confirmar.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  const pasos = document.querySelectorAll('.paso');
  const navItems = document.querySelectorAll('.paso-item');
  let pasoActual = 1;

  function mostrarPaso(n) {
    pasos.forEach(p => p.style.display = (p.dataset.paso == n) ? 'block' : 'none');
    navItems.forEach(item => item.classList.toggle('activo', item.dataset.pasoNav == n));
    pasoActual = n;
    if (n === 2) generarConfigRounds();
    if (n === 3) filtrarAlumnosPorNivel();
    if (n === 4) generarResumen();
  }

  document.querySelectorAll('.btn-siguiente').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!validarPaso(pasoActual)) return;
      mostrarPaso(pasoActual + 1);
    });
  });

  document.querySelectorAll('.btn-anterior').forEach(btn => {
    btn.addEventListener('click', () => mostrarPaso(pasoActual - 1));
  });

  function validarPaso(n) {
    if (n === 1) {
      const nombre = document.getElementById('nombre').value.trim();
      const fecha = document.getElementById('fecha').value;
      const institucion = document.getElementById('institucion_organizadora_id').value;
      if (!nombre || !fecha) {
        mostrarMensaje('Completá nombre y fecha del torneo.', 'error');
        return false;
      }
      if (!institucion) {
        mostrarMensaje('Elegí la institución del torneo.', 'error');
        return false;
      }
    }
    if (n === 2) {
      const algunoElegido = document.querySelectorAll('.chk-nivel:checked').length > 0;
      if (!algunoElegido) {
        mostrarMensaje('Elegí al menos un level.', 'error');
        return false;
      }
    }
    return true;
  }

  // ---- PASO 2: generar inputs de rounds por cada nivel elegido ----
  function generarConfigRounds() {
    const contenedor = document.getElementById('config-rounds-por-nivel');
    contenedor.innerHTML = '';

    document.querySelectorAll('.chk-nivel:checked').forEach(chk => {
      const nivelId = chk.value;
      const codigo = chk.dataset.codigo;

      const bloque = document.createElement('div');
      bloque.className = 'bloque-nivel-rounds';
      bloque.innerHTML = `
        <h4>${codigo}</h4>
        <div class="campo">
          <label>Cantidad de rounds</label>
          <input type="number" min="1" max="10" value="3" class="input-cant-rounds" data-nivel="${nivelId}">
        </div>
        <div class="filas-rounds" data-nivel-filas="${nivelId}"></div>
      `;
      contenedor.appendChild(bloque);

      const inputCant = bloque.querySelector('.input-cant-rounds');
      generarFilasRounds(nivelId, parseInt(inputCant.value));
      inputCant.addEventListener('input', () => {
        generarFilasRounds(nivelId, parseInt(inputCant.value) || 1);
      });
    });
  }

  function generarFilasRounds(nivelId, cantidad) {
    const cont = document.querySelector(`[data-nivel-filas="${nivelId}"]`);
    cont.innerHTML = '';
    for (let r = 1; r <= cantidad; r++) {
      const esUltimo = r === cantidad;
      const fila = document.createElement('div');
      fila.className = 'fila-round';
      fila.innerHTML = `
        <span>Round ${r}${esUltimo ? ' (final)' : ''}</span>
        <label>
          Pasan a la siguiente ronda:
          <input type="number" min="1" name="rounds[${nivelId}][${r}][pasan]"
                 ${esUltimo ? 'placeholder="Ganador"' : 'required'}>
        </label>
      `;
      cont.appendChild(fila);
    }
  }

  // ---- PASO 1: cambiar el texto del label según el alcance elegido ----
  document.querySelectorAll('.radio-alcance').forEach(radio => {
    radio.addEventListener('change', () => {
      const label = document.getElementById('label-institucion');
      const esInteres = document.querySelector('.radio-alcance:checked').value === 'interescolar';
      label.textContent = esInteres ? 'Institución organizadora' : 'Institución del torneo';
    });
  });

  // ---- PASO 3: mostrar solo los grupos de alumnos de los niveles elegidos ----
  function filtrarAlumnosPorNivel() {
    const codigosElegidos = Array.from(document.querySelectorAll('.chk-nivel:checked'))
      .map(chk => chk.dataset.codigo);

    const tipoAlcance = document.querySelector('.radio-alcance:checked').value;
    const institucionId = document.getElementById('institucion_organizadora_id').value;

    document.querySelectorAll('.grupo-alumnos-nivel').forEach(grupo => {
      const visiblePorNivel = codigosElegidos.includes(grupo.dataset.nivelCodigo);
      grupo.style.display = visiblePorNivel ? 'block' : 'none';

      grupo.querySelectorAll('.fila-alumno').forEach(fila => {
        let visible = visiblePorNivel;

        // Si es institucional, además filtramos por institución elegida en el paso 1
        if (visible && tipoAlcance === 'institucional') {
          visible = fila.dataset.institucionId === institucionId;
        }

        fila.style.display = visible ? 'flex' : 'none';
        if (!visible) {
          fila.querySelector('input[type="checkbox"]').checked = false;
        }
      });
    });
  }

  // ---- PASO 4: resumen antes de confirmar ----
  function generarResumen() {
    const nombre = document.getElementById('nombre').value;
    const fecha = document.getElementById('fecha').value;
    const formato = document.querySelector('input[name="formato"]:checked').value;
    const tipoAlcance = document.querySelector('.radio-alcance:checked').value;
    const institucionTexto = document.getElementById('institucion_organizadora_id').selectedOptions[0].text;
    const usaAudio = document.querySelector('input[name="usa_audio"]').checked;
    const niveles = Array.from(document.querySelectorAll('.chk-nivel:checked'))
      .map(chk => chk.dataset.codigo).join(', ');
    const totalAlumnos = document.querySelectorAll('input[name="alumnos[]"]:checked').length;

    document.getElementById('resumen-confirmacion').innerHTML = `
      <p><strong>Nombre:</strong> ${nombre}</p>
      <p><strong>Fecha:</strong> ${fecha}</p>
      <p><strong>Alcance:</strong> ${tipoAlcance === 'interescolar' ? 'Interescolar' : 'Institucional'}</p>
      <p><strong>Institución:</strong> ${institucionTexto}</p>
      <p><strong>Formato:</strong> ${formato === 'deletreo_oracion' ? 'Deletreo + Oración' : 'Solo deletreo'}</p>
      <p><strong>Audio:</strong> ${usaAudio ? 'Sí' : 'No'}</p>
      <p><strong>Levels:</strong> ${niveles || '—'}</p>
      <p><strong>Alumnos inscriptos:</strong> ${totalAlumnos}</p>
    `;
  }

  mostrarPaso(1);
});
