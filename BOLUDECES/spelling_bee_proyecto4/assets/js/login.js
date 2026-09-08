// ============================================================
// LOGIN.JS — validaciones propias de la vista de login
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    if (!email || !password) {
      e.preventDefault();
      mostrarMensaje('Completá email y contraseña.', 'error');
    }
  });
});
