<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
$config = obter_configuracoes();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['nome_estabelecimento'] ?? 'Hotel') ?></title>
    <?php require __DIR__ . '/includes/pwa_head.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">
<header class="bg-blue-700 text-white py-8 text-center px-4">
    <h1 class="text-3xl font-bold"><?= e($config['nome_estabelecimento'] ?? '') ?></h1>
    <p class="text-blue-100 mt-2"><?= e($config['descricao'] ?? '') ?></p>
</header>

<main class="max-w-xl mx-auto p-4 sm:p-8 space-y-4">
    <a href="reservas.php" class="block text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">
        Fazer uma reserva
    </a>
    <a href="cancelar.php" class="block text-center bg-white border border-gray-300 text-gray-800 py-3 rounded-lg">
        Cancelar uma reserva
    </a>
    <a href="termos.php" class="block text-center text-blue-700 underline text-sm">
        Termos, condições e política de cancelamento
    </a>

    <button id="btn-instalar" type="button"
            class="hidden w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg">
        📲 Instalar a aplicação
    </button>
    <p id="dica-ios" class="hidden text-sm text-gray-700 bg-yellow-50 border border-yellow-200 rounded p-3">
        Para instalar no iPhone: toque em <strong>Partilhar</strong> (⬆️) e depois em
        <strong>Adicionar ao ecrã principal</strong>.
    </p>

    <section class="bg-white p-5 rounded-lg shadow text-sm text-gray-700 space-y-1">
        <h2 class="font-semibold text-gray-800 mb-2">Contactos</h2>
        <p>📞 <?= e($config['telefone'] ?? '') ?></p>
        <p>✉️ <?= e($config['email'] ?? '') ?></p>
        <p>📍 <?= e($config['endereco'] ?? '') ?></p>
    </section>
    <p class="text-center text-xs text-slate-400"><a href="login.php" class="hover:text-slate-600">Área reservada</a></p>
</main>
<?php require __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>