<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/servicos_admin_funcoes.php';
require_once __DIR__ . '/includes/cancelamento_funcoes.php';
require_once __DIR__ . '/includes/publico_layout.php';

$c      = config_site();
$regras = listar_regras_cancelamento();

function classe_pct(int $p): string
{
    return $p >= 100 ? 'bg-folha-50 text-folha-600'
         : ($p > 0   ? 'bg-amber-50 text-amber-800' : 'bg-rose-50 text-rose-700');
}

publico_topo('Termos e condições | ' . ($c['nome_estabelecimento'] ?? 'Hotel'));
?>
<section class="bg-marca-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-ouro-400">Informação</p>
        <h1 class="mt-2 font-display text-3xl sm:text-4xl">Termos, condições e cancelamento</h1>
        <p class="mt-2 max-w-xl text-sm text-marca-200">Leia com atenção antes de reservar. É o que aceita ao fazer a sua reserva.</p>
    </div>
</section>

<main class="mx-auto max-w-4xl space-y-14 px-4 py-12 sm:px-6">

    <section>
        <h2 class="font-display text-2xl text-marca-900">Regras da casa</h2>
        <div class="mt-4 rounded-2xl border border-marca-100 bg-marca-50/50 p-6 leading-relaxed text-slate-700">
            <?= nl2br(e($c['regras_gerais'] ?? '')) ?>
        </div>
    </section>

    <section>
        <h2 class="font-display text-2xl text-marca-900">Política de cancelamento</h2>
        <p class="mt-2 text-sm text-slate-600">
            O reembolso depende da antecedência com que cancela, contada até às 00:00 do dia de início da reserva.
            Se for o hotel a cancelar, reembolsamos sempre 100% do valor.
        </p>

        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <?php foreach (TIPOS_SERVICO as $tipo => $rotulo): ?>
                <?php if (empty($regras[$tipo])) continue; ?>
                <article class="rounded-2xl border border-marca-100 bg-white p-6 shadow-sm">
                    <h3 class="flex items-center gap-3 font-display text-lg text-marca-900">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-marca-50 text-marca-700"><?= icone_pub($tipo, 'h-5 w-5') ?></span>
                        <?= e($rotulo) ?>
                    </h3>
                    <ul class="mt-4 divide-y divide-slate-100 text-sm">
                        <?php $anterior = null; ?>
                        <?php foreach ($regras[$tipo] as $r): ?>
                            <?php
                            $h = (int)$r['horas_antecedencia_min'];
                            $p = (int)$r['percentagem_reembolso'];
                            $texto = $anterior === null
                                ? 'Com ' . formatar_antecedencia($h) . ' ou mais'
                                : 'De ' . formatar_antecedencia($h) . ' até menos de ' . formatar_antecedencia($anterior);
                            $anterior = $h;
                            ?>
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span class="text-slate-700"><?= e($texto) ?></span>
                                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold <?= classe_pct($p) ?>"><?= $p ?>% de reembolso</span>
                            </li>
                        <?php endforeach; ?>
                        <li class="flex items-center justify-between gap-3 py-3">
                            <span class="text-slate-700">Com menos de <?= e(formatar_antecedencia((int)$anterior)) ?> ou sem comparência</span>
                            <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Sem reembolso</span>
                        </li>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="mt-6 text-sm">
            Precisa de cancelar?
            <a href="<?= e(BASE_PATH) ?>/cancelar.php" class="font-semibold text-marca-700 underline">Cancele a sua reserva aqui</a>.
        </p>
    </section>

    <section>
        <h2 class="font-display text-2xl text-marca-900">Termos e condições</h2>
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 leading-relaxed text-slate-700">
            <?= nl2br(e($c['termos_condicoes'] ?? '')) ?>
        </div>
    </section>
</main>
<?php publico_fundo(); ?>