<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/reservas_funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/servicos_admin_funcoes.php';

$u = exigir_permissao('servicos.gerir');

// ===== Ativar / desativar =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf($_POST['csrf'] ?? null)) {
        flash('erro', 'Sessão inválida. Recarregue a página e tente novamente.');
    } else {
        $id     = (int)($_POST['id'] ?? 0);
        $ativar = ($_POST['ativo'] ?? '') === '1';
        try {
            $nome = definir_estado_servico($id, $ativar);
            registar_auditoria($ativar ? 'servico_ativado' : 'servico_desativado', "ID $id: $nome");
            flash('ok', $ativar ? "«$nome» ativado." : "«$nome» desativado. Já não aparece para novas reservas.");
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
        }
    }
    redirecionar(BASE_PATH . '/admin/servicos.php');
}

$servicos = listar_servicos_admin();

admin_topo('Serviços', 'servicos');
?>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Preços, capacidade e disponibilidade dos serviços reserváveis.</p>
    <a href="<?= e(BASE_PATH) ?>/admin/servico_editar.php"
       class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">+ Novo serviço</a>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Serviço</th>
                    <th class="px-4 py-3">Preço</th>
                    <th class="px-4 py-3">Capacidade</th>
                    <th class="px-4 py-3">Comodidades</th>
                    <th class="px-4 py-3">Reservas ativas</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($servicos as $s): ?>
                <tr class="<?= $s['ativo'] ? '' : 'bg-slate-50/60 text-slate-500' ?>">
                    <td class="px-4 py-3">
                        <p class="font-medium <?= $s['ativo'] ? 'text-slate-900' : 'text-slate-600' ?>"><?= e($s['nome']) ?></p>
                        <p class="text-xs text-slate-500"><?= e(TIPOS_SERVICO[$s['tipo']] ?? $s['tipo']) ?></p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <p class="font-semibold"><?= e(formatar_mt($s['preco'])) ?></p>
                        <p class="text-xs text-slate-500"><?= e(ROTULO_UNIDADE[$s['unidade']] ?? '') ?></p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <p>Até <?= (int)$s['capacidade'] ?> pessoa(s)</p>
                        <p class="text-xs text-slate-500"><?= (int)$s['quantidade'] ?> unidade(s)</p>
                    </td>
                    <td class="px-4 py-3"><?= (int)$s['n_comodidades'] ?></td>
                    <td class="px-4 py-3"><?= (int)$s['n_reservas_ativas'] ?></td>
                    <td class="px-4 py-3">
                        <?php if ($s['ativo']): ?>
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Ativo</span>
                        <?php else: ?>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <a href="<?= e(BASE_PATH) ?>/admin/servico_editar.php?id=<?= (int)$s['id'] ?>"
                           class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</a>

                        <?php
                        $aviso = null;
                        if ($s['ativo'] && (int)$s['n_reservas_ativas'] > 0) {
                            $aviso = 'Este serviço tem ' . (int)$s['n_reservas_ativas']
                                   . ' reserva(s) pendente(s) ou confirmada(s). Elas mantêm-se, mas deixará de aceitar novas reservas. Continuar?';
                        }
                        ?>
                        <form method="post" class="ml-1 inline"<?= $aviso ? ' data-confirmar="' . e($aviso) . '"' : '' ?>>
                            <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <input type="hidden" name="ativo" value="<?= $s['ativo'] ? '0' : '1' ?>">
                            <button class="rounded-md px-3 py-1.5 text-xs font-semibold <?= $s['ativo'] ? 'border border-rose-200 bg-white text-rose-700 hover:bg-rose-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>">
                                <?= $s['ativo'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php admin_fundo(); ?>