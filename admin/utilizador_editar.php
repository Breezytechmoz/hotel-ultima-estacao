<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/utilizadores_admin_funcoes.php';

$u  = exigir_permissao('utilizadores.gerir');
$id = (int)($_GET['id'] ?? 0);
$id = $id > 0 ? $id : null;

$alvo = null;
if ($id !== null) {
    try {
        $alvo = garantir_gestao_permitida(obter_utilizador($id), (int)$u['id']);
    } catch (RuntimeException $ex) {
        flash('erro', $ex->getMessage());
        redirecionar(BASE_PATH . '/admin/utilizadores.php');
    }
}

$novo         = $alvo === null;
$erros        = [];
$senha_gerada = null;
$mensagem     = '';
$v = [
    'nome'  => $alvo['nome']  ?? '',
    'email' => $alvo['email'] ?? '',
    'papel' => $alvo['papel'] ?? 'gestor',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';

    } elseif (!$novo && ($_POST['acao'] ?? '') === 'repor') {
        $senha_gerada = repor_password_utilizador($id);
        registar_auditoria('password_reposta', "ID $id: {$alvo['email']}");   // a palavra-passe NUNCA vai para a auditoria
        $mensagem = 'Palavra-passe reposta. Todas as sessões desta conta foram terminadas.';

    } else {
        [$erros, $nome, $email, $papel] = validar_dados_utilizador($_POST, $novo);
        $v = ['nome' => $nome, 'email' => $novo ? $email : $alvo['email'], 'papel' => $papel];

        if (!$erros) {
            try {
                if ($novo) {
                    [$novo_id, $senha_gerada] = criar_utilizador_web($nome, $email, $papel);
                    registar_auditoria('utilizador_criado', "ID $novo_id: $email ($papel)");
                    $mensagem = 'Conta criada com sucesso.';
                } else {
                    atualizar_utilizador($id, $nome, $papel);
                    $detalhe = "ID $id: {$alvo['email']}";
                    if ($alvo['papel'] !== $papel) {
                        $detalhe .= "; papel {$alvo['papel']} → $papel";
                    }
                    registar_auditoria('utilizador_editado', $detalhe);
                    flash('ok', 'Utilizador atualizado.');
                    redirecionar(BASE_PATH . '/admin/utilizadores.php');
                }
            } catch (RuntimeException $ex) {
                $erros[] = $ex->getMessage();
            }
        }
    }
}

if ($senha_gerada !== null) {
    header('Cache-Control: no-store, max-age=0');
}

admin_topo($novo ? 'Novo utilizador' : 'Editar utilizador', 'utilizadores');
?>
<?php if ($senha_gerada !== null): ?>

    <div class="max-w-xl rounded-xl border border-emerald-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900"><?= e($mensagem) ?></h2>
        <p class="mt-1 text-sm text-slate-500">Entregue estes dados à pessoa em causa.</p>

        <dl class="mt-5 space-y-4 text-sm">
            <div>
                <dt class="text-xs font-medium text-slate-500">Email de acesso</dt>
                <dd class="mt-1 text-slate-900"><?= e($v['email']) ?></dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-slate-500">Palavra-passe temporária</dt>
                <dd class="mt-1 select-all rounded-lg bg-slate-900 px-4 py-3 font-mono text-lg tracking-wide text-emerald-300"><?= e($senha_gerada) ?></dd>
            </div>
        </dl>

        <ul class="mt-5 list-disc space-y-1 pl-5 text-xs text-slate-600">
            <li><strong>Só é mostrada agora.</strong> Se a perder, é preciso repor outra.</li>
            <li>No primeiro acesso a pessoa é obrigada a escolher uma palavra-passe própria.</li>
            <li>Entregue em mão ou por um canal privado. Não a envie para grupos nem para emails partilhados.</li>
        </ul>

        <a href="<?= e(BASE_PATH) ?>/admin/utilizadores.php"
           class="mt-6 inline-block rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Concluir</a>
    </div>

<?php else: ?>

    <?php if ($erros): ?>
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <ul class="list-disc space-y-1 pl-5">
                <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">

        <div>
            <label for="nome" class="mb-1.5 block text-sm font-medium text-slate-700">Nome completo</label>
            <input type="text" id="nome" name="nome" maxlength="100" required value="<?= e($v['nome']) ?>" class="<?= CLASSE_INPUT ?>">
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
            <?php if ($novo): ?>
                <input type="email" id="email" name="email" maxlength="100" required autocomplete="off" value="<?= e($v['email']) ?>" class="<?= CLASSE_INPUT ?>">
            <?php else: ?>
                <p class="rounded-lg bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700"><?= e($v['email']) ?></p>
                <p class="mt-1.5 text-xs text-slate-500">O email identifica a conta e não pode ser alterado.</p>
            <?php endif; ?>
        </div>

        <div>
            <label for="papel" class="mb-1.5 block text-sm font-medium text-slate-700">Papel</label>
            <select id="papel" name="papel" class="<?= CLASSE_INPUT ?>">
                <?php foreach (PAPEIS_ATRIBUIVEIS as $chave => $rotulo): ?>
                    <option value="<?= e($chave) ?>" <?= $v['papel'] === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="mt-1.5 text-xs text-slate-500">
                <strong>Gestor:</strong> reservas, serviços e configurações. <strong>Financeiro:</strong> valores e relatórios, sem dados de contacto dos clientes.
                <strong>Cliente:</strong> sem acesso ao painel.
            </p>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
            <a href="<?= e(BASE_PATH) ?>/admin/utilizadores.php" class="text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
            <button class="rounded-lg bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                <?= $novo ? 'Criar conta' : 'Guardar alterações' ?>
            </button>
        </div>
    </form>

    <?php if (!$novo): ?>
        <section class="mt-6 max-w-xl rounded-xl border border-amber-200 bg-amber-50/50 p-6">
            <h2 class="font-semibold text-slate-900">Repor palavra-passe</h2>
            <p class="mt-1 text-sm text-slate-600">
                Gera uma palavra-passe temporária nova e termina as sessões abertas desta conta.
            </p>
            <form method="post" class="mt-4" data-confirmar="<?= e('Repor a palavra-passe de ' . $alvo['nome'] . '? As sessões abertas serão terminadas.') ?>">
                <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                <input type="hidden" name="acao" value="repor">
                <button class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50">Repor palavra-passe</button>
            </form>
        </section>
    <?php endif; ?>

<?php endif; ?>
<?php admin_fundo(); ?>