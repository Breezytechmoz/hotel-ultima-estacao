<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/servicos_admin_funcoes.php';

$u = exigir_permissao('servicos.gerir');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf($_POST['csrf'] ?? null)) {
        flash('erro', 'Sessão inválida. Recarregue a página e tente novamente.');
    } else {
        $acao = (string)($_POST['acao'] ?? '');
        $id   = (int)($_POST['id'] ?? 0);
        $pdo  = conectar();

        try {
            if ($acao === 'apagar') {
                $st = $pdo->prepare('SELECT nome, (SELECT COUNT(*) FROM servico_comodidade WHERE comodidade_id = c.id) AS n
                                     FROM comodidades c WHERE c.id = :id');
                $st->execute([':id' => $id]);
                $c = $st->fetch();
                if (!$c) {
                    throw new RuntimeException('Comodidade não encontrada.');
                }
                $pdo->prepare('DELETE FROM comodidades WHERE id = :id')->execute([':id' => $id]);
                registar_auditoria('comodidade_apagada', "ID $id: {$c['nome']} (estava em {$c['n']} serviço(s))");
                flash('ok', "«{$c['nome']}» apagada.");

            } elseif ($acao === 'criar' || $acao === 'editar') {
                [$erros, $nome, $icone] = validar_comodidade($_POST);
                if ($erros) {
                    throw new RuntimeException(implode(' ', $erros));
                }
                try {
                    if ($acao === 'criar') {
                        $pdo->prepare('INSERT INTO comodidades (nome, icone) VALUES (:n, :i)')
                            ->execute([':n' => $nome, ':i' => $icone]);
                        registar_auditoria('comodidade_criada', $nome);
                        flash('ok', "«$nome» criada.");
                    } else {
                        $pdo->prepare('UPDATE comodidades SET nome = :n, icone = :i WHERE id = :id')
                            ->execute([':n' => $nome, ':i' => $icone, ':id' => $id]);
                        registar_auditoria('comodidade_editada', "ID $id: $nome");
                        flash('ok', "«$nome» atualizada.");
                    }
                } catch (PDOException $ex) {
                    if ($ex->getCode() === '23000') {
                        throw new RuntimeException('Já existe uma comodidade com esse nome.');
                    }
                    throw $ex;
                }
            } else {
                throw new RuntimeException('Ação desconhecida.');
            }
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
        }
    }
    redirecionar(BASE_PATH . '/admin/comodidades.php');
}

$comodidades = conectar()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM servico_comodidade sc WHERE sc.comodidade_id = c.id) AS n_servicos
     FROM comodidades c ORDER BY c.nome'
)->fetchAll();

admin_topo('Comodidades', 'comodidades');
?>
<section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-3 font-semibold text-slate-900">Nova comodidade</h2>
    <form method="post" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
        <input type="hidden" name="acao" value="criar">
        <div class="min-w-56 flex-1">
            <label for="novo-nome" class="mb-1.5 block text-xs font-medium text-slate-600">Nome</label>
            <input type="text" id="novo-nome" name="nome" maxlength="80" required placeholder="Ex.: Pequeno-almoço incluído" class="<?= CLASSE_INPUT ?>">
        </div>
        <div class="w-28">
            <label for="novo-icone" class="mb-1.5 block text-xs font-medium text-slate-600">Ícone (emoji)</label>
            <input type="text" id="novo-icone" name="icone" maxlength="10" placeholder="🍳" class="<?= CLASSE_INPUT ?>">
        </div>
        <button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Adicionar</button>
    </form>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <ul class="divide-y divide-slate-100">
        <?php foreach ($comodidades as $c): ?>
            <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                <form method="post" class="flex min-w-72 flex-1 items-center gap-2">
                    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="text" name="icone" value="<?= e($c['icone']) ?>" maxlength="10" aria-label="Ícone"
                           class="w-16 rounded-lg border border-slate-300 px-2 py-2 text-center text-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30">
                    <input type="text" name="nome" value="<?= e($c['nome']) ?>" maxlength="80" required aria-label="Nome"
                           class="<?= CLASSE_INPUT ?>">
                    <button class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Guardar</button>
                </form>

                <span class="w-28 text-xs text-slate-500">Em <?= (int)$c['n_servicos'] ?> serviço(s)</span>

                <form method="post"
                      data-confirmar="<?= e('Apagar «' . $c['nome'] . '»? Será removida de ' . (int)$c['n_servicos'] . ' serviço(s).') ?>">
                    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                    <input type="hidden" name="acao" value="apagar">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="rounded-md border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Apagar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php admin_fundo(); ?>