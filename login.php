<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';

enviar_cabecalhos_seguranca(true);

if ($u = utilizador_atual()) {
    redirecionar(pagina_inicial_do_papel($u['papel']));
}

$config = obter_configuracoes();
$erro   = null;
$email  = '';
$aviso  = $_SESSION['aviso_login'] ?? null;
unset($_SESSION['aviso_login']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = mb_substr(trim($_POST['email'] ?? ''), 0, 100);
    $password = (string)($_POST['password'] ?? '');

    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erro = 'Sessão inválida. Recarregue a página e tente novamente.';
    } else {
        $r = tentar_login($email, $password);
        if ($r['ok']) {
            redirecionar(pagina_inicial_do_papel($r['papel']));
        }
        $erro = $r['erro'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar | <?= e($config['nome_estabelecimento'] ?? 'Hotel') ?></title>
    <?php require __DIR__ . '/includes/pwa_head.php'; ?>
</head>
<body class="min-h-screen bg-white">
<div class="grid min-h-screen lg:grid-cols-2">

    <section class="relative hidden flex-col justify-between bg-linear-to-br from-blue-700 via-blue-800 to-slate-900 p-12 text-white lg:flex">
        <div class="flex items-center gap-3">
            <img src="<?= e(BASE_PATH) ?>/assets/img/icons/icon-192.png" alt="" class="h-10 w-10 rounded-xl">
            <span class="text-lg font-semibold"><?= e($config['nome_estabelecimento'] ?? '') ?></span>
        </div>
        <div>
            <h2 class="text-4xl font-semibold leading-tight">Gestão de reservas,<br>simples e segura.</h2>
            <p class="mt-4 max-w-md text-blue-100">
                Alojamento, rent-a-car, eventos e catering num só sistema.
            </p>
        </div>
        <p class="text-sm text-blue-200">Cidade de Tete, Moçambique</p>
    </section>

    <section class="flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Bem-vindo de volta</h1>
            <p class="mt-1 text-sm text-slate-500">Entre com a sua conta para continuar.</p>

            <?php if ($aviso): ?>
                <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><?= e($aviso) ?></div>
            <?php endif; ?>
            <?php if ($erro): ?>
                <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= e($erro) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-5" novalidate>
                <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus
                           autocomplete="username" maxlength="100"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30">
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Palavra-passe</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               autocomplete="current-password" maxlength="128"
                               class="w-full rounded-lg border border-slate-300 py-2.5 pl-3.5 pr-20 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30">
                        <button type="button" data-alternar="#password"
                                class="absolute inset-y-0 right-0 px-3.5 text-xs font-medium text-slate-500 hover:text-slate-800">Mostrar</button>
                    </div>
                </div>

                <button class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-hidden focus:ring-2 focus:ring-blue-600/40">
                    Entrar
                </button>
            </form>

            <p class="mt-8 text-center text-sm">
                <a href="<?= e(BASE_PATH) ?>/index.php" class="text-slate-500 hover:text-slate-800">← Voltar ao site</a>
            </p>
        </div>
    </section>
</div>
<script src="<?= e(BASE_PATH) ?>/assets/js/admin.js" defer></script>
</body>
</html>