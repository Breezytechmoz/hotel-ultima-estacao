<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/reservas_funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/servicos_admin_funcoes.php';

$u = exigir_permissao('servicos.gerir');

$id = (int)($_GET['id'] ?? 0);
$id = $id > 0 ? $id : null;

$servico = null;
if ($id !== null) {
    $servico = obter_servico($id);
    if (!$servico) {
        flash('erro', 'Serviço não encontrado.');
        redirecionar(BASE_PATH . '/admin/servicos.php');
    }
}
$novo         = $servico === null;
$todas        = listar_comodidades();
$selecionadas = $novo ? [] : ids_comodidades_do_servico($id);
$erros        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';
    } else {
        $r     = ler_dados_servico($_POST, $novo);
        $erros = $r['erros'];
        $d     = $r['dados'];

        // só aceita comodidades que existem de verdade
        $pedidas      = array_map('intval', (array)($_POST['comodidades'] ?? []));
        $validas      = array_map(fn($c) => (int)$c['id'], $todas);
        $selecionadas = array_values(array_intersect($pedidas, $validas));

        if (!$erros) {
            try {
                $guardado = guardar_servico($id, $d, $selecionadas);

                if ($novo) {
                    registar_auditoria('servico_criado', "ID $guardado: {$d['nome']} ({$d['tipo']}) a {$d['preco']}");
                } else {
                    $detalhe = "ID $guardado: {$d['nome']}";
                    if ($servico['preco'] !== $d['preco']) {
                        $detalhe .= "; preço {$servico['preco']} → {$d['preco']}";
                    }
                    registar_auditoria('servico_editado', $detalhe);
                }
                flash('ok', $novo ? 'Serviço criado com sucesso.' : 'Serviço atualizado com sucesso.');
                redirecionar(BASE_PATH . '/admin/servicos.php');

            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $erros[] = 'Já existe um serviço com este nome.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}

$v = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $_POST
    : ($servico ?? ['nome' => '', 'descricao' => '', 'preco' => '', 'capacidade' => 2, 'quantidade' => 1, 'ativo' => 1]);

admin_topo($novo ? 'Novo serviço' : 'Editar serviço', 'servicos');
?>
<?php if ($erros): ?>
    <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
        <ul class="list-disc space-y-1 pl-5">
            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="max-w-3xl space-y-6">
    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">

    <section class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-semibold text-slate-900">Informação geral</h2>

        <div>
            <label for="nome" class="mb-1.5 block text-sm font-medium text-slate-700">Nome</label>
            <input type="text" id="nome" name="nome" maxlength="100" required value="<?= e($v['nome'] ?? '') ?>" class="<?= CLASSE_INPUT ?>">
        </div>

        <div>
            <span class="mb-1.5 block text-sm font-medium text-slate-700">Tipo</span>
            <?php if ($novo): ?>
                <select name="tipo" class="<?= CLASSE_INPUT ?>" required>
                    <option value="">Escolha...</option>
                    <?php foreach (TIPOS_SERVICO as $chave => $rotulo): ?>
                        <option value="<?= e($chave) ?>" <?= ($v['tipo'] ?? '') === $chave ? 'selected' : '' ?>>
                            <?= e($rotulo) ?> (cobrado <?= e(ROTULO_UNIDADE[UNIDADE_POR_TIPO[$chave]]) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1.5 text-xs text-slate-500">O tipo não pode ser alterado depois, porque define as regras de cancelamento e de cobrança.</p>
            <?php else: ?>
                <p class="rounded-lg bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700">
                    <?= e(TIPOS_SERVICO[$servico['tipo']] ?? $servico['tipo']) ?> · cobrado <?= e(ROTULO_UNIDADE[$servico['unidade']] ?? '') ?>
                </p>
            <?php endif; ?>
        </div>

        <div>
            <label for="descricao" class="mb-1.5 block text-sm font-medium text-slate-700">Descrição</label>
            <textarea id="descricao" name="descricao" rows="3" maxlength="1000" class="<?= CLASSE_INPUT ?>"><?= e($v['descricao'] ?? '') ?></textarea>
        </div>
    </section>

    <section class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-semibold text-slate-900">Preço e disponibilidade</h2>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label for="preco" class="mb-1.5 block text-sm font-medium text-slate-700">Preço (MT)</label>
                <input type="text" inputmode="decimal" id="preco" name="preco" required value="<?= e((string)($v['preco'] ?? '')) ?>" placeholder="2500" class="<?= CLASSE_INPUT ?>">
            </div>
            <div>
                <label for="capacidade" class="mb-1.5 block text-sm font-medium text-slate-700">Capacidade (pessoas)</label>
                <input type="number" id="capacidade" name="capacidade" min="1" max="1000" required value="<?= e((string)($v['capacidade'] ?? '')) ?>" class="<?= CLASSE_INPUT ?>">
            </div>
            <div>
                <label for="quantidade" class="mb-1.5 block text-sm font-medium text-slate-700">Unidades disponíveis</label>
                <input type="number" id="quantidade" name="quantidade" min="1" max="500" required value="<?= e((string)($v['quantidade'] ?? '')) ?>" class="<?= CLASSE_INPUT ?>">
            </div>
        </div>

        <?php if (!$novo): ?>
            <p class="rounded-lg bg-amber-50 px-3.5 py-2.5 text-xs text-amber-800">
                Alterar o preço <strong>não afeta as reservas já feitas</strong>, que guardam o preço da altura.
                Se reduzir as unidades abaixo das reservas já existentes nas mesmas datas, essas reservas mantêm-se.
            </p>
        <?php endif; ?>

        <label class="flex items-center gap-2.5 text-sm text-slate-700">
            <input type="checkbox" name="ativo" value="1" class="h-4 w-4 accent-blue-600" <?= !empty($v['ativo']) ? 'checked' : '' ?>>
            Ativo (aceita novas reservas)
        </label>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-semibold text-slate-900">Comodidades incluídas</h2>
        <?php if (!$todas): ?>
            <p class="mt-3 text-sm text-slate-500">Ainda não há comodidades. <a class="text-blue-700 underline" href="<?= e(BASE_PATH) ?>/admin/comodidades.php">Crie algumas</a>.</p>
        <?php else: ?>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($todas as $c): ?>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        <input type="checkbox" name="comodidades[]" value="<?= (int)$c['id'] ?>" class="h-4 w-4 accent-blue-600"
                               <?= in_array((int)$c['id'], $selecionadas, true) ? 'checked' : '' ?>>
                        <span><?= e($c['icone']) ?> <?= e($c['nome']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="flex items-center justify-end gap-3">
        <a href="<?= e(BASE_PATH) ?>/admin/servicos.php" class="text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
        <button class="rounded-lg bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
            <?= $novo ? 'Criar serviço' : 'Guardar alterações' ?>
        </button>
    </div>
</form>
<?php admin_fundo(); ?>