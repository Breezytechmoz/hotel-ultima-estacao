<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/reservas_funcoes.php';
require_once __DIR__ . '/includes/servicos_admin_funcoes.php';   // nomes dos tipos e unidades
require_once __DIR__ . '/includes/publico_layout.php';

$nome_hotel = config_site()['nome_estabelecimento'] ?? 'Hotel';
$servicos   = listar_servicos();
$erros      = [];

$old = [
    'servico_id' => 0, 'nome' => '', 'email' => '', 'telefone' => '',
    'data_inicio' => '', 'data_fim' => '', 'num_pessoas' => 1, 'observacoes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'servico_id'  => (int)($_POST['servico_id'] ?? 0),
        'nome'        => trim((string)($_POST['nome'] ?? '')),
        'email'       => mb_strtolower(trim((string)($_POST['email'] ?? ''))),
        'telefone'    => trim((string)($_POST['telefone'] ?? '')),
        'data_inicio' => trim((string)($_POST['data_inicio'] ?? '')),
        'data_fim'    => trim((string)($_POST['data_fim'] ?? '')),
        'num_pessoas' => (int)($_POST['num_pessoas'] ?? 0),
        'observacoes' => trim((string)($_POST['observacoes'] ?? '')),
    ];

    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';
    } else {
        $servico = $servicos[$old['servico_id']] ?? null;
        if (!$servico) {
            $erros[] = 'Escolha um serviço válido.';
        }

        $tam = mb_strlen($old['nome']);
        if ($tam < 3 || $tam > 100) {
            $erros[] = 'O nome deve ter entre 3 e 100 caracteres.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 100) {
            $erros[] = 'O email introduzido não é válido.';
        }
        if (!preg_match('/^\+?[0-9\s\-]{9,20}$/', $old['telefone'])) {
            $erros[] = 'O telefone não é válido (ex.: +258 84 000 0000).';
        }
        if (mb_strlen($old['observacoes']) > 500) {
            $erros[] = 'As observações ultrapassam 500 caracteres.';
        }

        if ($servico && $servico['unidade'] === 'evento') {
            $old['data_fim'] = $old['data_inicio'];          // eventos têm uma só data
        }

        $ini = parse_data($old['data_inicio']);
        $fim = parse_data($old['data_fim']);

        if (!$ini || !$fim) {
            $erros[] = 'Indique datas válidas.';
        } else {
            $hoje = new DateTimeImmutable('today');
            if ($ini < $hoje) {
                $erros[] = 'A data de início não pode estar no passado.';
            } elseif ($fim < $ini) {
                $erros[] = 'A data final não pode ser anterior à inicial.';
            } elseif ($servico && $servico['unidade'] === 'noite' && $fim == $ini) {
                $erros[] = 'A estadia mínima é de 1 noite.';
            }
        }

        if ($servico && ($old['num_pessoas'] < 1 || $old['num_pessoas'] > (int)$servico['capacidade'])) {
            $erros[] = 'O número de pessoas deve ser entre 1 e ' . (int)$servico['capacidade'] . '.';
        }
        if (empty($_POST['aceito_termos'])) {
            $erros[] = 'Tem de aceitar os Termos e Condições para reservar.';
        }

        if (empty($erros)) {
            try {
                $codigo = criar_reserva($old['servico_id'], $old, $ini, $fim);
                registar_auditoria('reserva_criada', $codigo);
                $_SESSION['reserva_ok'] = ['codigo' => $codigo, 'servico' => $servico['nome'], 'email' => $old['email']];
                redirecionar(BASE_PATH . '/reservas.php');
            } catch (RuntimeException $ex) {
                $erros[] = $ex->getMessage();
            }
        }
    }
}

$sucesso = $_SESSION['reserva_ok'] ?? null;
unset($_SESSION['reserva_ok']);

publico_topo('Reservar | ' . $nome_hotel);
?>
<section class="bg-marca-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-ouro-400">Reservas</p>
        <h1 class="mt-2 font-display text-3xl sm:text-4xl">Faça a sua reserva</h1>
        <p class="mt-2 max-w-xl text-sm text-marca-200">
            Escolha o serviço, indique as datas e deixe os seus dados. A nossa equipa confirma a reserva consigo.
        </p>
    </div>
</section>

<?php if ($sucesso): ?>

    <section class="mx-auto max-w-xl px-4 py-14 sm:px-6">
        <div class="rounded-2xl border border-folha-500/30 bg-folha-50 p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-widest text-folha-600">Reserva registada</p>
            <p class="mt-3 text-sm text-slate-600"><?= e($sucesso['servico']) ?></p>
            <p class="mt-4 select-all rounded-xl bg-white px-4 py-4 font-mono text-2xl font-semibold tracking-wide text-marca-800 ring-1 ring-marca-100">
                <?= e($sucesso['codigo']) ?>
            </p>
            <p class="mt-5 text-sm text-slate-600">
                A reserva está <strong>pendente</strong> até a nossa equipa a confirmar.
                <strong>Guarde este código</strong> e o email <strong><?= e($sucesso['email']) ?></strong>: são precisos para consultar ou cancelar a reserva.
            </p>
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                <a href="<?= e(BASE_PATH) ?>/index.php" class="rounded-lg bg-marca-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-marca-800">Voltar ao início</a>
                <a href="<?= e(BASE_PATH) ?>/termos.php" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Política de cancelamento</a>
            </div>
        </div>
    </section>

<?php elseif (!$servicos): ?>

    <section class="mx-auto max-w-xl px-4 py-14 text-center sm:px-6">
        <p class="rounded-2xl bg-marca-50 p-8 text-slate-600">
            De momento não há serviços disponíveis para reserva. Contacte-nos para mais informações.
        </p>
    </section>

<?php else: ?>

    <form id="form-reserva" method="post" class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-3">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">

        <div class="space-y-10 lg:col-span-2">

            <?php if ($erros): ?>
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">
                        <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section>
                <h2 class="flex items-center gap-3 font-display text-xl text-marca-900">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-marca-700 font-sans text-sm font-semibold text-white">1</span>
                    Escolha o serviço
                </h2>
                <div class="mt-5 space-y-3">
                    <?php foreach ($servicos as $s): ?>
                        <?php
                        $foto = foto_tipo($s['tipo']);
                        $com  = $s['lista_comodidades'];
                        ?>
                        <label class="block cursor-pointer">
                            <input type="radio" name="servico_id" value="<?= (int)$s['id'] ?>" required
                                   data-nome="<?= e($s['nome']) ?>" data-unidade="<?= e($s['unidade']) ?>"
                                   data-preco="<?= e((string)$s['preco']) ?>" data-capacidade="<?= (int)$s['capacidade'] ?>"
                                   class="peer sr-only" <?= $old['servico_id'] === (int)$s['id'] ? 'checked' : '' ?>>
                            <div class="flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-marca-300 peer-checked:border-marca-600 peer-checked:ring-2 peer-checked:ring-marca-600/30 peer-focus-visible:ring-2 peer-focus-visible:ring-marca-400">
                                <?php if ($foto): ?>
                                    <img src="<?= e($foto) ?>" alt="" loading="lazy" class="hidden w-36 shrink-0 object-cover sm:block">
                                <?php else: ?>
                                    <div class="hidden w-36 shrink-0 items-center justify-center bg-marca-50 text-marca-600 sm:flex">
                                        <?= icone_pub($s['tipo'], 'h-10 w-10') ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-display text-lg text-marca-900"><?= e($s['nome']) ?></h3>
                                            <p class="text-xs text-slate-500"><?= e(TIPOS_SERVICO[$s['tipo']] ?? '') ?> · até <?= (int)$s['capacidade'] ?> pessoa(s)</p>
                                        </div>
                                        <p class="text-right">
                                            <span class="block whitespace-nowrap font-semibold text-marca-700"><?= e(formatar_mt($s['preco'])) ?></span>
                                            <span class="text-xs text-slate-500"><?= e(ROTULO_UNIDADE[$s['unidade']] ?? '') ?></span>
                                        </p>
                                    </div>
                                    <?php if (!empty($s['descricao'])): ?>
                                        <p class="mt-2 text-sm text-slate-600"><?= e($s['descricao']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($com): ?>
                                        <ul class="mt-3 flex flex-wrap gap-1.5">
                                            <?php foreach (array_slice($com, 0, 6) as $c): ?>
                                                <li class="rounded-full bg-marca-50 px-2.5 py-1 text-xs text-marca-800"><?= e($c) ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($com) > 6): ?>
                                                <li class="px-1 py-1 text-xs text-slate-500">+<?= count($com) - 6 ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section>
                <h2 class="flex items-center gap-3 font-display text-xl text-marca-900">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-marca-700 font-sans text-sm font-semibold text-white">2</span>
                    Datas
                </h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label id="rotulo-inicio" for="data_inicio" class="mb-1.5 block text-sm font-medium text-slate-700">Data de início</label>
                        <input type="date" id="data_inicio" name="data_inicio" required min="<?= date('Y-m-d') ?>"
                               value="<?= e($old['data_inicio']) ?>" class="<?= CLASSE_CAMPO ?>">
                    </div>
                    <div id="bloco-fim">
                        <label id="rotulo-fim" for="data_fim" class="mb-1.5 block text-sm font-medium text-slate-700">Data final</label>
                        <input type="date" id="data_fim" name="data_fim" min="<?= date('Y-m-d') ?>"
                               value="<?= e($old['data_fim']) ?>" class="<?= CLASSE_CAMPO ?>">
                    </div>
                </div>
            </section>

            <section>
                <h2 class="flex items-center gap-3 font-display text-xl text-marca-900">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-marca-700 font-sans text-sm font-semibold text-white">3</span>
                    Os seus dados
                </h2>
                <div class="mt-5 space-y-4">
                    <div>
                        <label for="nome" class="mb-1.5 block text-sm font-medium text-slate-700">Nome completo</label>
                        <input type="text" id="nome" name="nome" required maxlength="100" autocomplete="name"
                               value="<?= e($old['nome']) ?>" class="<?= CLASSE_CAMPO ?>">
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                            <input type="email" id="email" name="email" required maxlength="100" autocomplete="email"
                                   value="<?= e($old['email']) ?>" class="<?= CLASSE_CAMPO ?>">
                        </div>
                        <div>
                            <label for="telefone" class="mb-1.5 block text-sm font-medium text-slate-700">Telefone</label>
                            <input type="tel" id="telefone" name="telefone" required maxlength="30" autocomplete="tel"
                                   placeholder="+258 84 000 0000" value="<?= e($old['telefone']) ?>" class="<?= CLASSE_CAMPO ?>">
                        </div>
                    </div>
                    <div class="sm:w-48">
                        <label for="num_pessoas" class="mb-1.5 block text-sm font-medium text-slate-700">Número de pessoas</label>
                        <input type="number" id="num_pessoas" name="num_pessoas" required min="1"
                               value="<?= (int)$old['num_pessoas'] ?>" class="<?= CLASSE_CAMPO ?>">
                    </div>
                    <div>
                        <label for="observacoes" class="mb-1.5 block text-sm font-medium text-slate-700">Observações <span class="font-normal text-slate-400">(opcional)</span></label>
                        <textarea id="observacoes" name="observacoes" rows="3" maxlength="500" class="<?= CLASSE_CAMPO ?>"><?= e($old['observacoes']) ?></textarea>
                    </div>
                </div>
            </section>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="rounded-2xl border border-marca-100 bg-marca-50/50 p-6 shadow-sm">
                <h2 class="font-display text-xl text-marca-900">Resumo</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Serviço</dt><dd id="resumo-servico" class="text-right font-medium text-slate-800">—</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Datas</dt><dd id="resumo-datas" class="text-right font-medium text-slate-800">—</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Duração</dt><dd id="resumo-duracao" class="text-right font-medium text-slate-800">—</dd></div>
                </dl>
                <div class="mt-5 border-t border-marca-100 pt-4">
                    <p class="text-xs text-slate-500">Total estimado</p>
                    <p id="resumo-total" class="mt-1 text-3xl font-semibold text-marca-800">—</p>
                </div>
                <p class="mt-3 text-xs text-slate-500">O valor final é confirmado ao reservar. A reserva fica pendente até confirmação da nossa equipa.</p>

                <label class="mt-5 flex items-start gap-2.5 text-sm text-slate-700">
                    <input type="checkbox" name="aceito_termos" value="1" class="mt-0.5 h-4 w-4 shrink-0 accent-marca-700">
                    <span>Li e aceito os
                        <a href="<?= e(BASE_PATH) ?>/termos.php" target="_blank" rel="noopener" class="text-marca-700 underline">Termos, Condições e Política de Cancelamento</a>.
                    </span>
                </label>

                <button class="mt-5 w-full rounded-lg bg-ouro-400 px-4 py-3 font-semibold text-marca-950 shadow transition hover:bg-ouro-300">
                    Reservar
                </button>
            </div>
        </aside>
    </form>

<?php endif; ?>
<?php publico_fundo(['reservas.js']); ?>