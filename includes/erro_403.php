<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acesso negado</title>
    <?php require __DIR__ . '/pwa_head.php'; ?>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-6">
    <main class="max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <p class="text-5xl font-bold text-slate-300">403</p>
        <h1 class="mt-4 text-xl font-semibold text-slate-900">Sem permissão</h1>
        <p class="mt-2 text-sm text-slate-600">
            A sua conta não tem acesso a esta área. Se acha que é um engano, contacte o administrador.
        </p>
        <a href="<?= e(pagina_inicial_do_papel(utilizador_atual()['papel'] ?? 'cliente')) ?>"
           class="mt-6 inline-block rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
            Voltar
        </a>
    </main>
</body>
</html>