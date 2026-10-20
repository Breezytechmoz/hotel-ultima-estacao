<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/cancelamento_funcoes.php';

$config = obter_configuracoes();
$regras = listar_regras_cancelamento();

$nomes_tipo = [
    'quarto'     => 'Alojamento (quartos)',
    'rent_a_car' => 'Rent-a-car',
    'evento'     => 'Sala de eventos',
    'catering'   => 'Catering e ornamentação',
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Termos e Condições | <?= e($config['nome_estabelecimento'] ?? 'Hotel') ?></title>
    <?php require __DIR__ . '/includes/pwa_scripts.php'; ?>
    <?php require __DIR__ . '/includes/pwa_head.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">
<header class="bg-blue-700 text-white py-6 text-center">
    <h1 class="text-2xl font-bold"><?= e($config['nome_estabelecimento'] ?? '') ?></h1>
    <p class="text-blue-100 text-sm mt-1">Termos, Condições e Política de Cancelamento</p>
</header>

<main class="max-w-3xl mx-auto p-4 sm:p-8 space-y-6">

    <section class="bg-white p-5 rounded-lg shadow">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Regras da casa</h2>
        <p class="text-gray-700"><?= nl2br(e($config['regras_gerais'] ?? '')) ?></p>
    </section>

    <section class="bg-white p-5 rounded-lg shadow">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Termos e condições</h2>
        <p class="text-gray-700"><?= nl2br(e($config['termos_condicoes'] ?? '')) ?></p>
    </section>

    <section class="bg-white p-5 rounded-lg shadow">
        <h2 class="text-lg font-semibold text-gray-800 mb-1">Política de cancelamento e reembolso</h2>
        <p class="text-sm text-gray-500 mb-4">
            A percentagem depende da antecedência com que cancela, contada até ao dia de início da reserva.
        </p>

        <?php foreach ($nomes_tipo as $tipo => $nome): ?>
            <?php if (empty($regras[$tipo])) continue; ?>
            <h3 class="font-semibold text-gray-900 mt-4"><?= e($nome) ?></h3>
            <ul class="list-disc ml-5 text-gray-700 text-sm space-y-1 mt-1">
                <?php foreach ($regras[$tipo] as $r): ?>
                    <li>
                        Cancelamento com <strong><?= e(formatar_antecedencia((int)$r['horas_antecedencia_min'])) ?></strong>
                        ou mais de antecedência: reembolso de <strong><?= (int)$r['percentagem_reembolso'] ?>%</strong>.
                    </li>
                <?php endforeach; ?>
                <li>Com menos antecedência, ou em caso de não comparência: sem reembolso.</li>
            </ul>
        <?php endforeach; ?>

        <p class="text-sm mt-5">
            Precisa de cancelar? <a href="cancelar.php" class="text-blue-700 underline">Cancele a sua reserva aqui</a>.
        </p>
    </section>
</main>
</body>
</html>