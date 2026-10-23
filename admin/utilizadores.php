<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/utilizadores_admin_funcoes.php';

$u = exigir_permissao('utilizadores.gerir');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf($_POST['csrf'] ?? null)) {
        flash('erro', 'Sessão inválida. Recarregue a página e tente novamente.');
    } else {
        $id     = (int)($_POST['id'] ?? 0);
        $ativar = ($_POST['ativo'] ?? '') === '1';
        try {
            $alvo = garantir_gestao_permitida(obter_utilizador($id), (int)$u['id']);
            definir_estado_utilizador($id, $ativar);
            registar_auditoria($ativar ? 'utilizador_ativado' : 'utilizador_desativado', "ID $id: {$alvo['email']}");
            flash('ok', $ativar ? "Conta de {$alvo['nome']} ativada." : "Conta de {$alvo['nome']} desativada e sessões terminadas.");
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
        }
    }
    redirecionar(BASE_PATH . '/admin/utilizadores.php');
}

$utilizadores = listar_utilizadores();

admin_topo('Utilizadores', 'utilizadores');
?>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Quem tem acesso ao sistema e com que nível de permissão.</p>
    <a href="<?= e(BASE_PATH) ?>/admin/utilizador_editar.php"
       class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">+ Novo utilizador</a>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Utilizador</th>
                    <th class="px-4 py-3">Papel</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Último acesso</th>
                    <th class="px-4 py-3 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($utilizadores as $x): ?>
                <?php
                $e_super = $x['papel'] === 'super_admin';
                $sou_eu  = (int)$x['id'] === (int)$u['id'];
                ?>
                <tr class="<?= $x['ativo'] ? '' : 'bg-slate-50/60 text-slate-500' ?>">
                    <td class="px-4 py-3">
                        <p class="font-medium <?= $x['ativo'] ? 'text-slate-900' : 'text-slate-600' ?>"><?= e($x['nome']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($x['email']) ?></p>
                    </td>
                    <td class="px-4 py-3"><?= badge_papel($x['papel']) ?></td>
                    <td class="px-4 py-3">
                        <?php if (!$x['ativo']): ?>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">Inativo</span>
                        <?php elseif ($x['deve_mudar_password']): ?>
                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Aguarda 1.º acesso</span>
                        <?php else: ?>
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Ativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                        <?= $x['ultimo_login_em'] ? e(fmt_data_hora($x['ultimo_login_em'])) : '—' ?>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <?php if ($e_super || $sou_eu): ?>
                            <span class="text-xs text-slate-400"><?= $sou_eu ? 'Você' : 'Gerido pelo terminal' ?></span>
                        <?php else: ?>
                            <a href="<?= e(BASE_PATH) ?>/admin/utilizador_editar.php?id=<?= (int)$x['id'] ?>"
                               class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>

                            <form method="post" class="ml-1 inline"
                                  <?= $x['ativo'] ? 'data-confirmar="' . e('Desativar ' . $x['nome'] . '? A pessoa será desligada de imediato.') . '"' : '' ?>>
                                <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
                                <input type="hidden" name="ativo" value="<?= $x['ativo'] ? '0' : '1' ?>">
                                <button class="rounded-md px-3 py-1.5 text-xs font-semibold <?= $x['ativo'] ? 'border border-rose-200 bg-white text-rose-700 hover:bg-rose-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>">
                                    <?= $x['ativo'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php admin_fundo(); ?>