(() => {
  // Confirmação antes de ações sensíveis:  <form data-confirmar="Tem a certeza?">
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset ? e.target.dataset.confirmar : null;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Mostrar/ocultar palavra-passe:  <button type="button" data-alternar="#password">
  document.addEventListener('click', (e) => {
    const botao = e.target.closest('[data-alternar]');
    if (!botao) return;
    const campo = document.querySelector(botao.dataset.alternar);
    if (!campo) return;
    const mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    botao.textContent = mostrar ? 'Ocultar' : 'Mostrar';
  });
})();