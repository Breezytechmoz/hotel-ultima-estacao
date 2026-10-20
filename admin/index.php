<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/reservas_funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$u = exigir_permissao('painel.ver');
$ver_valores   = pode('financeiro.ver');
$ver_contactos = pode('clientes.contactos');

$hoje    = date('Y-m-d');
$mes_ini = date('Y-m-01');
$mes_fim = date('Y-m-01', strtotime('first day of next month'));

$st = conectar()->prepare(
    "SELECT
        COALESCE(SUM(estado = 'pendente'), 0)                                              AS pendentes,
        COALESCE(SUM(estado = 'confirmada' AND data_inicio >= :h1), 0)                     AS proximas,
        COALESCE(SUM(estado IN ('pendente','confirmada') AND data_inicio = :h2), 0)        AS chegadas_hoje,
        COALESCE(SUM(CASE WHEN estado IN ('confirmada','concluida')
                           AND data_inicio >= :i1 AND data_inicio < :f1
                          THEN preco_total END), 0)                                        AS valor_mes,
        COALESCE(SUM(CASE WHEN estado = 'cancelada'
                           AND cancelado_em >= :i2 AND cancelado_em < :f2
                          THEN valor_reembolso END), 0)                                    AS reembolsos_mes
     FROM reservas"
);
$st->execute([
    ':h1' => $hoje, ':h2' => $hoje,
    ':i1' => $mes_ini, ':f1' => $mes_fim,
    ':i2' => $mes_ini, ':f2' => $mes_fim,
]);
$m = $st->fetch();

$pendentes = conectar()->query(
    "SELECT r.id, r.codigo, r.nome_cliente, r.data_inicio, r.data_fim, r.preco_total, s.nome AS servico
     FROM reservas r JOIN servicos s ON s.id = r.servico_id
     WHERE r.estado = 'pendente'
     ORDER BY r.criado_em DESC LIMIT 5"
)->fetchAll();

admin_topo('Painel', 'painel');
?>
<p class="mb-6 text-sm text-slate-500">Olá, <strong class="text-slate-800"><?= e(explode(' ', $u['nome'])[0]) ?></strong>. Este é o resumo de hoje.</p>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?= cartao_metrica('Pendentes', (string)(int)$m['pendentes'], 'a aguardar confirmação', 'relogio', 'bg-amber-50 text-amber-600') ?>
    <?= cartao_metrica('Chegadas hoje', (string)(int)$m['chegadas_hoje'], 'reservas com início hoje', 'reservas', 'bg-blue-50 text-blue-600') ?>
    <?= cartao_metrica('Confirmadas', (string)(int)$m['proximas'], 'de hoje em diante', 'check', 'bg-emerald-50 text-emerald-600') ?>
    <?php if ($ver_valores): ?>
        <?= cartao_metrica('Valor reservado (mês)', formatar_mt($m['valor_mes']), 'reembolsos no mês: ' . formatar_mt($m['reembolsos_mes']), 'dinheiro', 'bg-violet-50 text-violet-600') ?>
    <?php endif; ?>
</div>

<section class="mt-8 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <h2 class="font-semibold text-slate-900">Reservas pendentes</h2>
        <a href="<?= e(BASE_PATH) ?>/admin/reservas.php?estado=pendente" class="text-sm font-medium text-blue-700 hover:text-blue-800">Ver todas →</a>
    </div>

    <?php if (!$pendentes): ?>
        <p class="px-5 py-10 text-center text-sm text-slate-500">Sem reservas pendentes. Bom trabalho! 🎉</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($pendentes as $r): ?>
                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5">
                    <div>
                        <p class="text-sm font-medium text-slate-900">
                            <?= e($r['servico']) ?>
                            <span class="ml-1 font-mono text-xs font-normal text-slate-400"><?= e($r['codigo']) ?></span>
                        </p>
                        <p class="text-xs text-slate-500">
                            <?= e(fmt_data($r['data_inicio'])) ?>
                            <?= $r['data_fim'] !== $r['data_inicio'] ? ' → ' . e(fmt_data($r['data_fim'])) : '' ?>
                            <?= $ver_contactos ? ' · ' . e($r['nome_cliente']) : '' ?>
                        </p>
                    </div>
                    <?php if ($ver_valores): ?>
                        <p class="text-sm font-semibold text-slate-700"><?= e(formatar_mt($r['preco_total'])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php admin_fundo(); ?>