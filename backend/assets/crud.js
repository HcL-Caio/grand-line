/* Comportamento mínimo do CRUD. Tudo funciona sem JavaScript; isto só acrescenta conforto. */
(() => {
  // Menu lateral no celular
  const sidebar = document.querySelector('#sidebar');
  const menu = document.querySelector('.app-menu');
  const scrim = document.querySelector('.sidebar-scrim');
  if (sidebar && menu && scrim) {
    const close = () => { sidebar.classList.remove('is-open'); scrim.hidden = true; menu.setAttribute('aria-expanded', 'false'); };
    menu.addEventListener('click', () => {
      const open = sidebar.classList.toggle('is-open');
      scrim.hidden = !open;
      menu.setAttribute('aria-expanded', String(open));
    });
    scrim.addEventListener('click', close);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  }

  // Confirmação antes de excluir: <form data-confirm="mensagem">
  document.addEventListener('submit', event => {
    const message = event.target instanceof HTMLFormElement ? event.target.dataset.confirm : '';
    if (message && !window.confirm(message)) event.preventDefault();
  });

  // Mostrar/ocultar senha
  document.querySelectorAll('[data-toggle-password]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Ocultar' : 'Mostrar';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
    });
  });

  // Mensagem de erro recebe foco para leitores de tela
  const alert = document.querySelector('.cx-flash-error[tabindex]');
  if (alert) alert.focus();
})();
