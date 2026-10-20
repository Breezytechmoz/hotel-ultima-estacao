<?php
// ===== Ícones (SVG simples, sem bibliotecas externas) =====
function icone(string $nome, string $classe = 'h-5 w-5'): string
{
    static $paths = [
        'painel'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'reservas' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'config'   => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'sair'     => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'dinheiro' => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20M16 15h2"/>',
        'relogio'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'    => '<path d="M5 13l4 4L19 7"/>',
    ];
    return '<svg class="' . e($classe) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$nome] ?? '') . '</svg>';
}

// ===== Formatação =====
function fmt_data(string $data): string
{
    return (new DateTimeImmutable($data))->format('d/m/Y');
}

function badge_estado(string $estado): string
{
    $mapa = [
        'pendente'   => ['Pendente',   'bg-amber-50 text-amber-700 ring-amber-600/20'],
        'confirmada' => ['Confirmada', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'cancelada'  => ['Cancelada',  'bg-rose-50 text-rose-700 ring-rose-600/20'],
        'concluida'  => ['Concluída',  'bg-sky-50 text-sky-700 ring-sky-600/20'],
    ];
    [$texto, $cls] = $mapa[$estado] ?? [$estado, 'bg-slate-50 text-slate-700 ring-slate-600/20'];

    return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '
         . $cls . '">' . e($texto) . '</span>';
}

function cartao_metrica(string $titulo, string $valor, string $nota, string $ico, string $cores): string
{
    return '<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">'
         . '<div class="flex items-center justify-between">'
         . '<p class="text-sm font-medium text-slate-500">' . e($titulo) . '</p>'
         . '<span class="rounded-lg p-2 ' . $cores . '">' . icone($ico) . '</span></div>'
         . '<p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">' . e($valor) . '</p>'
         . '<p class="mt-1 text-xs text-slate-500">' . e($nota) . '</p></div>';
}

// ===== Topo do painel =====
function admin_topo(string $titulo, string $ativo): void
{
    $u      = utilizador_atual();
    $flash  = obter_flash();
    $config = obter_configuracoes();
    $inicial = mb_strtoupper(mb_substr($u['nome'], 0, 1));

    $menu = [
        'painel'        => ['Painel',        '/admin/index.php',         'painel',   'painel.ver'],
        'reservas'      => ['Reservas',      '/admin/reservas.php',      'reservas', 'reservas.ver'],
        'configuracoes' => ['Configurações', '/admin/configuracoes.php', 'config',   'configuracoes.editar'],
    ];
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo) ?> | Painel</title>
    <?php require __DIR__ . '/pwa_head.php'; ?>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<input type="checkbox" id="menu-movel" class="peer sr-only" aria-label="Abrir menu">

<aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 transition-transform duration-200 peer-checked:translate-x-0 lg:translate-x-0">
    <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
        <img src="<?= e(BASE_PATH) ?>/assets/img/icons/icon-192.png" alt="" class="h-8 w-8 rounded-lg">
        <div class="leading-tight">
            <p class="truncate text-sm font-semibold text-white"><?= e($config['nome_estabelecimento'] ?? 'Hotel') ?></p>
            <p class="text-xs text-slate-400">Painel de gestão</p>
        </div>
    </div>

    <nav class="flex-1 space-y-1 p-3">
        <?php foreach ($menu as $chave => [$rotulo, $url, $ico, $perm]): ?>
            <?php if (!pode($perm)) continue; ?>
            <a href="<?= e(BASE_PATH . $url) ?>"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition <?= $chave === $ativo ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                <?= icone($ico) ?><?= e($rotulo) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white"><?= e($inicial) ?></span>
            <div class="min-w-0 leading-tight">
                <p class="truncate text-sm font-medium text-white"><?= e($u['nome']) ?></p>
                <p class="text-xs text-slate-400"><?= e(NOMES_PAPEIS[$u['papel']] ?? $u['papel']) ?></p>
            </div>
        </div>
        <form method="post" action="<?= e(BASE_PATH) ?>/logout.php" class="mt-3">
            <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
            <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-white/5 hover:text-white">
                <?= icone('sair', 'h-4 w-4') ?>Terminar sessão
            </button>
        </form>
    </div>
</aside>

<label for="menu-movel" class="fixed inset-0 z-30 hidden bg-slate-900/50 peer-checked:block lg:peer-checked:hidden"></label>

<div class="lg:pl-64">
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-8">
        <label for="menu-movel" class="-ml-2 cursor-pointer rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden">
            <?= icone('menu', 'h-6 w-6') ?>
        </label>
        <h1 class="text-lg font-semibold text-slate-900"><?= e($titulo) ?></h1>
    </header>

    <main class="mx-auto max-w-7xl p-4 sm:p-8">
        <?php if ($flash): ?>
            <div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= $flash['tipo'] === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?>">
                <?= e($flash['texto']) ?>
            </div>
        <?php endif; ?>
<?php
}

// ===== Fundo do painel =====
function admin_fundo(): void
{
    ?>
    </main>
</div>
<script src="<?= e(BASE_PATH) ?>/assets/js/admin.js" defer></script>
</body>
</html>
<?php
}