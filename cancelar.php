<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/reservas_funcoes.php';
require_once __DIR__ . '/includes/cancelamento_funcoes.php';

$config  = obter_configuracoes();
$erros   = [];
$reserva = null;
$calculo = null;

$codigo = trim($_POST['codigo'] ?? '');
$email  = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';
    } else {
        $reserva = buscar_reserva_cliente($codigo, $email);

        if (!$reserva) {
            $erros[] = 'Reserva não encontrada. Verifique o código e o email.';
        } elseif ($erro = validar_cancelavel($reserva)) {
            $erros[] = $erro;
            $reserva = null;
        } elseif (($_POST['acao'] ?? '') === 'confirmar') {
            try {
                $_SESSION['cancelamento_ok'] = cancelar_reserva($codigo, $email);
                header('Location: cancelar.php');
                exit;
            } catch (RuntimeException $ex) {
                $erros[] = $ex->getMessage();
                $reserva = null;
            }
        } else {
            $calculo = calcular_reembolso($reserva);   // só simula, não grava
        }
    }
}

$sucesso = $_SESSION['cancelamento_ok'] ?? null;
unset($_SESSION['cancelamento_ok']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cancelar reserva | <?= e($config['nome_estabelecimento'] ?? 'Hotel') ?></title>
    <?php require __DIR__ . '/includes/pwa_scripts.php'; ?>
    <?php require __DIR__ . '/includes/pwa_head.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">
<header class="bg-blue-700 text-white py-6 text-center">
    <h1 class="text-2xl font-bold"><?= e($config['nome_estabelecimento'] ?? '') ?></h1>
    <p class="text-blue-100 text-sm mt-1">Cancelar reserva</p>
</header>

<main class="max-w-xl mx-auto p-4 sm:p-8 space-y-6">

    <?php if ($sucesso): ?>
        <div class="p-4 rounded bg-green-100 text-green-800">
            <p class="font-semibold">Reserva <span class="font-mono"><?= e($sucesso['codigo']) ?></span> cancelada.</p>
            <p>Reembolso aplicado: <strong><?= (int)$sucesso['percentagem'] ?>%</strong>
               (<?= e(formatar_mt($sucesso['valor'])) ?>). A nossa equipa irá contactá-lo para processar a devolução.</p>
        </div>
    <?php endif; ?>

    <?php if ($erros): ?>
        <div class="p-3 rounded bg-red-100 text-red-800">
            <ul class="list-disc ml-5">
                <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($calculo): ?>
        <div class="bg-white p-5 rounded-lg shadow space-y-3">
            <h2 class="font-semibold text-gray-800">Confirme o cancelamento</h2>
            <p class="text-sm text-gray-700">
                <strong><?= e($reserva['servico_nome']) ?></strong><br>
                De <?= e($reserva['data_inicio']) ?> a <?= e($reserva['data_fim']) ?><br>
                Valor da reserva: <?= e(formatar_mt($reserva['preco_total'])) ?>
            </p>
            <p class="p-3 rounded bg-yellow-50 text-yellow-900 text-sm">
                Faltam cerca de <?= max(0, (int)$calculo['horas']) ?> horas para o início.<br>
                Reembolso: <strong><?= (int)$calculo['percentagem'] ?>%</strong>
                = <strong><?= e(formatar_mt($calculo['valor'])) ?></strong>
            </p>
            <form method="post">
                <input type="hidden" name="csrf"   value="<?= e(token_csrf()) ?>">
                <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                <input type="hidden" name="email"  value="<?= e($email) ?>">
                <input type="hidden" name="acao"   value="confirmar">
                <button class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded">
                    Confirmar cancelamento
                </button>
            </form>
        </div>
    <?php else: ?>
        <form method="post" class="bg-white p-5 rounded-lg shadow space-y-4">
            <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
            <input type="hidden" name="acao" value="consultar">
            <div>
                <label for="codigo" class="block text-sm font-medium text-gray-700 mb-1">Código da reserva</label>
                <input id="codigo" name="codigo" value="<?= e($codigo) ?>" placeholder="HUE-261025-A1B2C3"
                       class="w-full border border-gray-300 rounded p-2 font-mono">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email usado na reserva</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                       class="w-full border border-gray-300 rounded p-2">
            </div>
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded">
                Consultar reembolso
            </button>
            <p class="text-xs text-gray-500 text-center">
                Veja a <a href="termos.php" class="underline">política de cancelamento</a>.
            </p>
        </form>
    <?php endif; ?>
</main>
</body>
</html>