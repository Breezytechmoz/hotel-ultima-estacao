<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/reservas_funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/servicos_admin_funcoes.php';
require_once __DIR__ . '/../includes/financeiro_funcoes.php';

$u = exigir_permissao('financeiro.ver');

$mes      = ler_mes($_GET['mes'] ?? null);
$fim      = $mes->modify('+1 month');
$anterior = $mes->modify('-1 month')->format('Y-m');
$seguinte = $fim->format('Y-m');
$n_mes    = (int)$mes->format('n');

$resumo  = resumo_do_mes($mes, $fim);
$servs   = valor_por_servico($mes, $fim);
$por_mes = valor_por_mes_do_ano((int)$mes->format('Y'));
$origens = cancelamentos_por_origem($mes, $fim);

$valor_total    = (float)$resumo['valor_validas'];
$ticket         = (int)$resumo['n_validas'] > 0 ? $valor_total / (int)$resumo['n_validas'] : 0.0;
$maximo         = max($por_mes) > 0 ? max($por_mes) : 1.0;
$rotulos_origem = ['cliente' => 'Pelo cliente', 'hotel' => 'Pelo hotel'];

admin_topo('Financeiro', 'financeiro');
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <a href="?mes=<?= e($anterior) ?>" class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200 hover:bg-slate-50" aria-label="Mês anterior">←</a>
        <h2 class="min-w-44 text-center text-lg font-semibold text-slate-900"><?= e(MESES_LONGOS[$n_mes] . ' de ' . $mes->format('Y')) ?></h2>
        <a href="?mes=<?= e($seguinte) ?>" class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200 hover:bg-slate-50" aria-label="Mês seguinte">→</a>
    </div>
    <a href="<?= e(BASE_PATH) ?>/admin/financeiro_exportar.php?mes=<?= e($mes->format('Y-m')) ?>"
       class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
        Exportar CSV
    </a>
</div>

<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
    Os valores referem-se ao <strong>mês de início</strong> de cada reserva e representam o que foi <strong>reservado</strong>,
    e não o dinheiro já recebido. O registo de pagamentos será acrescentado mais tarde.
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?= cartao_metrica('Valor reservado', formatar_mt($valor_total), (int)$resumo['n_validas'] . ' reserva(s) confirmada(s) ou concluída(s)', 'dinheiro', 'bg-emerald-50 text-emerald-600') ?>
    <?= cartao_metrica('Valor médio por reserva', formatar_mt($ticket), 'média do mês', 'reservas', 'bg-blue-50 text-blue-600') ?>
    <?= cartao_metrica('Reembolsado', formatar_mt($resumo['reembolsado']), (int)$resumo['n_canceladas'] . ' cancelamento(s)', 'dinheiro', 'bg-rose-50 text-rose-600') ?>
    <?= cartao_metrica('Retido em cancelamentos', formatar_mt($resumo['retido']), 'valor mantido pelo hotel', 'dinheiro', 'bg-violet-50 text-violet-600') ?>
</div>

<p class="mt-3 text-xs text-slate-500">
    Ainda por confirmar: <?= (int)$resumo['n_pendentes'] ?> reserva(s) pendente(s), no valor de <?= e(formatar_mt($resumo['valor_pendente'])) ?>.
</p>

<section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-4 font-semibold text-slate-900">Valor reservado em <?= e($mes->format('Y')) ?></h2>
    <div class="overflow-x-auto">
        <svg viewBox="0 0 720 205" class="h-auto w-full min-w-[600px]" role="img" aria-label="Valor reservado por mês em <?= e($mes->format('Y')) ?>">
            <line x1="10" y1="170" x2="710" y2="170" class="stroke-slate-200" stroke-width="1"/>
            <?php foreach ($por_mes as $m => $valor): ?>
                <?php
                $altura = (int)round(($valor / $maximo) * 140);
                $x      = 24 + ($m - 1) * 57;
                ?>
                <rect x="<?= $x ?>" y="<?= 170 - $altura ?>" width="36" height="<?= $altura ?>" rx="4"
                      class="<?= $m === $n_mes ? 'fill-blue-700' : 'fill-blue-200' ?>">
                    <title><?= e(MESES_LONGOS[$m] . ': ' . formatar_mt($valor)) ?></title>
                </rect>
                <text x="<?= $x + 18 ?>" y="192" text-anchor="middle" class="fill-slate-500 text-[11px]"><?= e(MESES_CURTOS[$m]) ?></text>
            <?php endforeach; ?>
        </svg>
    </div>
</section>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-900">Por serviço</h2>
        <?php if (!$servs): ?>
            <p class="px-5 py-10 text-center text-sm text-slate-500">Sem reservas confirmadas neste mês.</p>
        <?php else: ?>
            <?php
            $linha_h = 34;
            $alt_svg = count($servs) * $linha_h + 8;
            $maior   = max(array_map(fn($s) => (float)$s['valor'], $servs));
            ?>
            <div class="overflow-x-auto border-b border-slate-100 px-5 py-4">
                <svg viewBox="0 0 720 <?= $alt_svg ?>" class="h-auto w-full min-w-[560px]" role="img"
                     aria-label="Valor reservado por serviço em <?= e($mes->format('Y-m')) ?>">
                    <?php foreach ($servs as $i => $s): ?>
                        <?php
                        $y    = 4 + $i * $linha_h;
                        $larg = $maior > 0 ? max(2, (int)round(((float)$s['valor'] / $maior) * 330)) : 2;
                        ?>
                        <text x="0" y="<?= $y + 19 ?>" class="fill-slate-700 text-[13px]"><?= e(mb_strimwidth($s['nome'], 0, 30, '…')) ?></text>
                        <rect x="250" y="<?= $y + 4 ?>" width="<?= $larg ?>" height="20" rx="4"
                              class="<?= $i === 0 ? 'fill-blue-700' : 'fill-blue-300' ?>">
                            <title><?= e($s['nome'] . ': ' . formatar_mt($s['valor'])) ?></title>
                        </rect>
                        <text x="<?= 250 + $larg + 8 ?>" y="<?= $y + 19 ?>" class="fill-slate-600 text-[12px]"><?= e(formatar_mt($s['valor'])) ?></text>
                    <?php endforeach; ?>
                </svg>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">Serviço</th><th class="px-3 py-3">Reservas</th><th class="px-3 py-3 text-right">Valor</th><th class="px-5 py-3 text-right">%</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($servs as $s): ?>
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-900"><?= e($s['nome']) ?></p>
                                <p class="text-xs text-slate-500"><?= e(TIPOS_SERVICO[$s['tipo']] ?? $s['tipo']) ?></p>
                            </td>
                            <td class="px-3 py-3"><?= (int)$s['n'] ?></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-semibold"><?= e(formatar_mt($s['valor'])) ?></td>
                            <td class="px-5 py-3 text-right text-slate-500"><?= $valor_total > 0 ? round((float)$s['valor'] / $valor_total * 100) : 0 ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-900">Cancelamentos</h2>
        <?php if (!$origens): ?>
            <p class="px-5 py-10 text-center text-sm text-slate-500">Sem cancelamentos neste mês.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">Origem</th><th class="px-3 py-3">Qtd.</th><th class="px-3 py-3 text-right">Valor das reservas</th><th class="px-5 py-3 text-right">Reembolsado</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($origens as $o): ?>
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-900"><?= e($rotulos_origem[$o['cancelado_por'] ?? ''] ?? 'Não registado') ?></td>
                            <td class="px-3 py-3"><?= (int)$o['n'] ?></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right"><?= e(formatar_mt($o['bruto'])) ?></td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold"><?= e(formatar_mt($o['reembolso'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php admin_fundo(); ?>