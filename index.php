<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/reservas_funcoes.php';
require_once __DIR__ . '/includes/servicos_admin_funcoes.php';   // nomes dos tipos e unidades
require_once __DIR__ . '/includes/publico_layout.php';

$config = config_site();
$nome   = $config['nome_estabelecimento'] ?? 'Hotel';
$hero   = is_file(__DIR__ . '/assets/img/fotos/hero.jpg');   // aparece quando juntares esta fotografia

$descricoes = [
    'quarto'     => 'Escolha o quarto que melhor se adapta a si ou à sua família.',
    'rent_a_car' => 'Viaturas para a cidade e para viagens mais longas.',
    'evento'     => 'Espaço para festas, casamentos e reuniões.',
    'catering'   => 'Comida e decoração para o seu evento.',
];

// Um cartão por tipo de serviço, com o preço mais baixo
$grupos = [];
foreach (listar_servicos() as $s) {
    $t = $s['tipo'];
    if (!isset($grupos[$t])) {
        $grupos[$t] = ['min' => (float)$s['preco'], 'unidade' => $s['unidade']];
    }
    $grupos[$t]['min'] = min($grupos[$t]['min'], (float)$s['preco']);
}

publico_topo($nome);
?>
<section class="relative overflow-hidden bg-marca-900 text-white">
    <?php if ($hero): ?>
        <img src="<?= e(BASE_PATH) ?>/assets/img/fotos/hero.jpg" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
    <?php endif; ?>
    <div class="absolute inset-0 bg-linear-to-br from-marca-950/90 via-marca-800/80 to-marca-700/60"></div>

    <div class="relative mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-ouro-400">Cidade de Tete</p>
        <h1 class="mt-4 max-w-2xl font-display text-4xl leading-tight sm:text-6xl"><?= e($nome) ?></h1>
        <p class="mt-5 max-w-xl text-lg text-marca-100"><?= e($config['descricao'] ?? '') ?></p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="<?= e(BASE_PATH) ?>/reservas.php"
               class="rounded-lg bg-ouro-400 px-6 py-3 font-semibold text-marca-950 shadow transition hover:bg-ouro-300">Reservar agora</a>
            <a href="#servicos"
               class="rounded-lg border border-white/30 px-6 py-3 font-semibold text-white transition hover:bg-white/10">Ver serviços</a>
        </div>
    </div>
</section>

<section id="servicos" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16 sm:px-6">
    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-ouro-600">O que oferecemos</p>
    <h2 class="mt-2 font-display text-3xl text-marca-900">Tudo num só lugar</h2>

    <?php if (!$grupos): ?>
        <p class="mt-10 rounded-2xl bg-marca-50 p-8 text-center text-slate-600">
            De momento não há serviços disponíveis para reserva. Contacte-nos para mais informações.
        </p>
    <?php else: ?>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($descricoes as $tipo => $texto): ?>
                <?php if (!isset($grupos[$tipo])) continue; ?>
                <?php $foto = foto_tipo($tipo); ?>
                <a href="<?= e(BASE_PATH) ?>/reservas.php"
                   class="group flex flex-col overflow-hidden rounded-2xl border border-marca-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-marca-300 hover:shadow-md">
                    <?php if ($foto): ?>
                        <img src="<?= e($foto) ?>" alt="" loading="lazy" class="h-40 w-full object-cover">
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-6">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-marca-50 text-marca-700 transition group-hover:bg-marca-700 group-hover:text-white">
                            <?= icone_pub($tipo) ?>
                        </span>
                        <h3 class="mt-5 font-display text-xl text-marca-900"><?= e(TIPOS_SERVICO[$tipo] ?? $tipo) ?></h3>
                        <p class="mt-2 flex-1 text-sm text-slate-600"><?= e($texto) ?></p>
                        <p class="mt-5 text-sm text-slate-500">
                            a partir de
                            <span class="block text-lg font-semibold text-marca-700"><?= e(formatar_mt($grupos[$tipo]['min'])) ?></span>
                            <span class="text-xs"><?= e(ROTULO_UNIDADE[$grupos[$tipo]['unidade']] ?? '') ?></span>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="bg-marca-50">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-4 py-12 sm:px-6">
        <div class="max-w-xl">
            <h2 class="font-display text-2xl text-marca-900">Leve-nos consigo</h2>
            <p class="mt-2 text-sm text-slate-600">
                Instale a nossa aplicação no telemóvel, sem lojas e sem ficheiros APK. No Android, toque em «Instalar».
                No iPhone, toque em Partilhar e depois em «Adicionar ao ecrã principal».
            </p>
        </div>
        <div>
            <button id="btn-instalar" type="button"
                    class="hidden rounded-lg bg-folha-600 px-6 py-3 font-semibold text-white shadow transition hover:bg-folha-500">
                Instalar a aplicação
            </button>
            <p id="dica-ios" class="hidden max-w-xs rounded-lg border border-ouro-300 bg-white p-3 text-sm text-slate-700">
                Toque em <strong>Partilhar</strong> e depois em <strong>Adicionar ao ecrã principal</strong>.
            </p>
        </div>
    </div>
</section>
<?php publico_fundo(); ?>