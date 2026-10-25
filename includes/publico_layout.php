<?php
if (!function_exists('fmt_data')) {
    function fmt_data(string $data): string
    {
        return (new DateTimeImmutable($data))->format('d/m/Y');
    }
}

const CLASSE_CAMPO = 'w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-marca-600 focus:outline-hidden focus:ring-2 focus:ring-marca-600/30';

function config_site(): array
{
    static $config = null;
    return $config ??= obter_configuracoes();      // uma só consulta por pedido
}

// Fotografia de cada tipo de serviço (assets/img/fotos/tipo-quarto.jpg, etc.), se existir
function foto_tipo(string $tipo): ?string
{
    $ficheiro = 'tipo-' . preg_replace('/[^a-z_]/', '', $tipo) . '.jpg';
    return is_file(__DIR__ . '/../assets/img/fotos/' . $ficheiro)
        ? BASE_PATH . '/assets/img/fotos/' . $ficheiro
        : null;
}

function icone_pub(string $nome, string $classe = 'h-6 w-6'): string
{
    static $paths = [
        'quarto'     => '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
        'rent_a_car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
        'evento'     => '<circle cx="9" cy="8" r="4"/><path d="M2 21v-1a6 6 0 0112 0v1"/><path d="M17 11a3 3 0 100-6M22 21v-1a5 5 0 00-4-4.9"/>',
        'catering'   => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
    ];
    return '<svg class="' . e($classe) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$nome] ?? '') . '</svg>';
}

function publico_topo(string $titulo): void
{
    enviar_cabecalhos_seguranca(true);     // política estrita: sem JavaScript escrito dentro das páginas

    $config = config_site();
    $nome   = $config['nome_estabelecimento'] ?? 'Hotel';
    $logo   = is_file(__DIR__ . '/../assets/img/marca/logo.png');   // quando existir o logótipo real, é usado
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?></title>
    <meta name="description" content="<?= e($config['descricao'] ?? '') ?>">
    <?php require __DIR__ . '/pwa_head.php'; ?>
</head>
<body class="bg-white text-slate-800 antialiased">
<header class="sticky top-0 z-30 border-b border-marca-100 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <a href="<?= e(BASE_PATH) ?>/index.php" class="flex items-center gap-3">
            <?php if ($logo): ?>
                <img src="<?= e(BASE_PATH) ?>/assets/img/marca/logo.png" alt="<?= e($nome) ?>" class="h-10 w-auto">
            <?php else: ?>
                <span class="leading-tight">
                    <span class="block text-[10px] font-semibold uppercase tracking-[0.25em] text-ouro-600">Hotel &amp; Restaurante</span>
                    <span class="block font-display text-xl italic text-marca-800">Última Estação</span>
                </span>
            <?php endif; ?>
        </a>
        <nav class="flex items-center gap-2 text-sm sm:gap-6">
            <a href="<?= e(BASE_PATH) ?>/cancelar.php" class="hidden text-slate-600 hover:text-marca-700 sm:inline">Cancelar reserva</a>
            <a href="<?= e(BASE_PATH) ?>/termos.php" class="hidden text-slate-600 hover:text-marca-700 sm:inline">Termos</a>
            <a href="<?= e(BASE_PATH) ?>/reservas.php" class="rounded-lg bg-marca-700 px-4 py-2 font-semibold text-white transition hover:bg-marca-800">Reservar</a>
        </nav>
    </div>
</header>
<?php
}

function publico_fundo(array $scripts = []): void
{
    $c        = config_site();
    $tel_href = preg_replace('/[^\d+]/', '', (string)($c['telefone'] ?? ''));
    ?>
<footer class="bg-marca-950 text-marca-200">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-3 sm:px-6">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.25em] text-ouro-400">Hotel &amp; Restaurante</p>
            <p class="mt-1 font-display text-2xl italic text-white">Última Estação</p>
            <p class="mt-3 max-w-xs text-sm text-marca-300"><?= e($c['descricao'] ?? '') ?></p>
        </div>
        <div class="text-sm">
            <h2 class="font-semibold text-white">Contactos</h2>
            <ul class="mt-3 space-y-2">
                <?php if (!empty($c['telefone'])): ?>
                    <li><a href="tel:<?= e($tel_href) ?>" class="hover:text-white"><?= e($c['telefone']) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($c['email'])): ?>
                    <li><a href="mailto:<?= e($c['email']) ?>" class="hover:text-white"><?= e($c['email']) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($c['endereco'])): ?>
                    <li><?= e($c['endereco']) ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="text-sm">
            <h2 class="font-semibold text-white">Informação</h2>
            <ul class="mt-3 space-y-2">
                <li><a href="<?= e(BASE_PATH) ?>/reservas.php" class="hover:text-white">Fazer uma reserva</a></li>
                <li><a href="<?= e(BASE_PATH) ?>/cancelar.php" class="hover:text-white">Cancelar uma reserva</a></li>
                <li><a href="<?= e(BASE_PATH) ?>/termos.php" class="hover:text-white">Termos e política de cancelamento</a></li>
                <li><a href="<?= e(BASE_PATH) ?>/login.php" class="text-marca-400 hover:text-white">Área reservada</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-marca-300">
        © <?= date('Y') ?> <?= e($c['nome_estabelecimento'] ?? '') ?>
    </div>
</footer>
<?php require __DIR__ . '/pwa_scripts.php'; ?>
<?php foreach ($scripts as $js): ?>
    <?php $js = basename($js); ?>
<script src="<?= e(BASE_PATH) ?>/assets/js/<?= e($js) ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/' . $js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}