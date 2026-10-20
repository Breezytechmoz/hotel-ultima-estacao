(() => {
  // Lê o caminho base escrito pelo PHP na etiqueta <script data-base="...">
  const base = document.currentScript.dataset.base || '/';

  // ===== 1. Registar o Service Worker =====
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker
        .register(base + 'sw.js', { scope: base })
        .catch(erro => console.error('Falha no Service Worker:', erro));
    });
  }

  // ===== 2. Botão "Instalar" (Android / Chrome) =====
  const botao = document.getElementById('btn-instalar');
  let eventoInstalar = null;

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();          // impede o mini-aviso automático
    eventoInstalar = e;          // guarda o evento para usar quando o cliente tocar
    if (botao) botao.classList.remove('hidden');
  });

  if (botao) {
    botao.addEventListener('click', async () => {
      if (!eventoInstalar) return;
      eventoInstalar.prompt();
      await eventoInstalar.userChoice;
      eventoInstalar = null;
      botao.classList.add('hidden');
    });
  }

  window.addEventListener('appinstalled', () => {
    if (botao) botao.classList.add('hidden');
  });

  // ===== 3. iPhone: não tem botão automático, só instruções =====
  const dicaIOS     = document.getElementById('dica-ios');
  const ehIOS       = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const jaInstalada = window.matchMedia('(display-mode: standalone)').matches
                      || navigator.standalone === true;

  if (dicaIOS && ehIOS && !jaInstalada) {
    dicaIOS.classList.remove('hidden');
  }
})();