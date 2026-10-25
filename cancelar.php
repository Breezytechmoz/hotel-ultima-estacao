<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/reservas_funcoes.php';
require_once __DIR__ . '/includes/servicos_admin_funcoes.php';
require_once __DIR__ . '/includes/cancelamento_funcoes.php';
require_once __DIR__ . '/includes/publico_layout.php';

$nome_hotel = config_site()['nome_estabelecimento'] ?? 'Hotel';
$ip      = ip_cliente();
$erros   = [];
$reserva = null;
$calculo = null;
$codigo  = strtoupper(mb_substr(trim((string)($_POST['codigo'] ?? '')), 0, 30));
$email   = mb_strtolower(mb_substr(trim((string)($_POST['email'] ?? '')), 0, 100));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';
    } elseif (consulta_bloqueada($ip)) {
        registar_auditoria('consulta_bloqueada', $codigo);
        $erros[] = 'Demasiadas tentativas. Aguarde ' . LOGIN_JANELA_MIN . ' minutos e tente novamente.';
    } else {
        $reserva = buscar_reserva_cliente($codigo, $email);

        if (!$reserva) {
            registar_falha_consulta($ip);
            $erros[] = 'Reserva não encontrada. Verifique o código e o email.';
        } elseif ($erro = validar_cancelavel($reserva)) {
            $erros[] = $erro;
            $reserva = null;
        } elseif (($_POST['acao'] ?? '') === 'confirmar') {
            try {
                $_SESSION['cancelamento_ok'] = cancelar_reserva($codigo, $email);
                registar_auditoria('reserva_cancelada_cliente', $codigo);
                redirecionar(BASE_PATH . '/cancelar.php');
            } catch (RuntimeException $ex) {
                $erros[] = $ex->getMessage();
                $reserva = null;
            }
        } else {
            $calculo = calcular_reembolso($reserva);       // só simula, não grava
        }
    }
}

$sucesso = $_SESSION['cancelamento_ok'] ?? null;
unset($_SESSION['cancelamento_ok']);

publico_topo('Cancelar reserva | ' . $nome_hotel);
?>
<section class="bg-marca-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-ouro-400">Reservas</p>
        <h1 class="mt-2 font-display text-3xl sm:text-4xl">Cancelar uma reserva</h1>
        <p class="mt-2 max-w-xl text-sm text-marca-200">Indique o código da reserva e o email usado. Antes de confirmar, mostramos quanto lhe é reembolsado.</p>
    </div>
</section>

<section class="mx-auto max-w-xl px-4 py-12 sm:px-6">

    <?php if ($erros): ?>
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <ul class="list-disc space-y-1 pl-5">
                <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>

        <div class="rounded-2xl border border-marca-100 bg-marca-50/50 p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-widest text-marca-600">Reserva cancelada</p>
            <p class="mt-3 font-mono text-lg font-semibold text-marca-800"><?= e($sucesso['codigo']) ?></p>
            <p class="mt-5 text-sm text-slate-600">Reembolso aplicado</p>
            <p class="mt-1 text-3xl font-semibold text-marca-800"><?= (int)$sucesso['percentagem'] ?>%
                <span class="text-lg font-normal text-slate-500">(<?= e(formatar_mt($sucesso['valor'])) ?>)</span></p>
            <p class="mt-5 text-sm text-slate-600">A nossa equipa irá contactá-lo para processar a devolução.</p>
            <a href="<?= e(BASE_PATH) ?>/index.php" class="mt-6 inline-block rounded-lg bg-marca-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-marca-800">Voltar ao início</a>
        </div>

    <?php elseif ($calculo): ?>
        <?php
        $pct = (int)$calculo['percentagem'];
        $cor = $pct >= 100 ? 'bg-folha-50 text-folha-600' : ($pct > 0 ? 'bg-amber-50 text-amber-800' : 'bg-rose-50 text-rose-700');
        $h   = max(0, (int)$calculo['horas']);
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-display text-xl text-marca-900">Confirme o cancelamento</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Serviço</dt><dd class="text-right font-medium"><?= e($reserva['servico_nome']) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Datas</dt>
                    <dd class="text-right font-medium"><?= e(fmt_data($reserva['data_inicio'])) ?><?= $reserva['data_fim'] !== $reserva['data_inicio'] ? ' → ' . e(fmt_data($reserva['data_fim'])) : '' ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Valor da reserva</dt><dd class="text-right font-medium"><?= e(formatar_mt($reserva['preco_total'])) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Antecedência</dt>
                    <dd class="text-right font-medium">cerca de <?= $h >= 48 ? intdiv($h, 24) . ' dias' : $h . ' horas' ?></dd></div>
            </dl>

            <div class="mt-5 rounded-xl p-4 text-center <?= $cor ?>">
                <p class="text-xs font-semibold uppercase tracking-widest">Reembolso</p>
                <p class="mt-1 text-3xl font-semibold"><?= $pct ?>%</p>
                <p class="text-sm"><?= e(formatar_mt($calculo['valor'])) ?></p>
            </div>
            <p class="mt-3 text-xs text-slate-500">Consulte a <a href="<?= e(BASE_PATH) ?>/termos.php" class="underline">política de cancelamento</a>.</p>

            <form method="post" class="mt-6 flex flex-wrap items-center gap-3" data-confirmar="Cancelar esta reserva? Esta ação não pode ser desfeita.">
                <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                <input type="hidden" name="email" value="<?= e($email) ?>">
                <input type="hidden" name="acao" value="confirmar">
                <button class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">Confirmar cancelamento</button>
                <a href="<?= e(BASE_PATH) ?>/cancelar.php" class="text-sm font-medium text-slate-600 hover:text-slate-900">Voltar</a>
            </form>
        </div>

    <?php else: ?>

        <form method="post" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
            <input type="hidden" name="acao" value="consultar">
            <div>
                <label for="codigo" class="mb-1.5 block text-sm font-medium text-slate-700">Código da reserva</label>
                <input id="codigo" name="codigo" required maxlength="30" autocomplete="off" placeholder="HUE-261025-A1B2C3"
                       value="<?= e($codigo) ?>" class="<?= CLASSE_CAMPO ?> font-mono">
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email usado na reserva</label>
                <input type="email" id="email" name="email" required maxlength="100" autocomplete="email"
                       value="<?= e($email) ?>" class="<?= CLASSE_CAMPO ?>">
            </div>
            <button class="w-full rounded-lg bg-marca-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-marca-800">Consultar reembolso</button>
        </form>

    <?php endif; ?>
</section>
<?php publico_fundo(['admin.js']); ?>