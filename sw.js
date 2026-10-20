// ===== 1. Configuração =====
const VERSAO = 'hue-v3';
const CACHE_ESTATICO = `estatico-${VERSAO}`;
const CACHE_PAGINAS  = `paginas-${VERSAO}`;

const BASE = new URL('./', self.location).pathname;   // ex.: /hotel-ultima-estacao/

const PRE_CACHE = [
  'offline.html',
  'manifest.json',
  'assets/css/app.css',
  'assets/js/pwa.js',
  'assets/img/icons/icon-192.png',
].map(p => BASE + p);

// Só estas páginas podem ser guardadas (não têm dados pessoais nem formulários)
const PAGINAS_CACHEAVEIS = [BASE, BASE + 'index.php', BASE + 'termos.php'];

// ===== 2. Instalação: guarda os ficheiros essenciais =====
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_ESTATICO)
      .then(cache => cache.addAll(PRE_CACHE))
      .then(() => self.skipWaiting())
  );
});

// ===== 3. Ativação: apaga caches de versões antigas =====
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then(chaves => Promise.all(
        chaves
          .filter(c => c !== CACHE_ESTATICO && c !== CACHE_PAGINAS)
          .map(c => caches.delete(c))
      ))
      .then(() => self.clients.claim())
  );
});

// ===== 4. Pedidos =====
self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;                       // formulários (POST) passam direto

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;        // só ficheiros do nosso site
  if (url.pathname.startsWith(BASE + 'admin/')) return;   // o painel nunca é interceptado

  if (req.mode === 'navigate') {
    event.respondWith(paginaRedeprimeiro(event));
  } else if (url.pathname.startsWith(BASE + 'assets/')) {
    event.respondWith(estaticoCacheprimeiro(req));
  }
});

// Páginas: tenta a internet; se falhar, usa a cópia ou a página offline
async function paginaRedeprimeiro(event) {
  const req = event.request;
  try {
    const resposta = await fetch(req);
    const caminho  = new URL(req.url).pathname;
    if (resposta.ok && PAGINAS_CACHEAVEIS.includes(caminho)) {
      const copia = resposta.clone();
      event.waitUntil(caches.open(CACHE_PAGINAS).then(c => c.put(req, copia)));
    }
    return resposta;
  } catch (erro) {
    return (await caches.match(req)) || (await caches.match(BASE + 'offline.html'));
  }
}

// CSS, JS e imagens: usa a cópia guardada; se não existir, vai à internet e guarda
async function estaticoCacheprimeiro(req) {
  const cache    = await caches.open(CACHE_ESTATICO);
  const guardado = await cache.match(req);
  if (guardado) return guardado;

  const resposta = await fetch(req);
  if (resposta.ok) cache.put(req, resposta.clone());
  return resposta;
}