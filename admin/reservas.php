<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/reservas_funcoes.php';
require_once __DIR__ . '/../includes/reservas_admin_funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$u             = exigir_permissao('reservas.ver');
$ver_contactos = pode('clientes.contactos');
$ver_valores   = pode('financeiro.ver');
$gerir         = pode('reservas.gerir');

// ===== 1. Filtros (validados) =====
$estados_validos = ['pendente', 'confirmada', 'cancelada', 'concluida'];
$estado = in_array($_GET['estado'] ?? '', $estados_validos, true) ? $_GET['estado'] : '';
$q      = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 50);
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

// ===== 2. Ações (confirmar / cancelar) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_permissao('reservas.gerir');            // "ver" não chega para "alterar"

    if (!validar_csrf($_POST['csrf'] ?? null)) {
        flash('erro', 'Sessão inválida. Recarregue a página e tente novamente.');
    } else {
        $id   = (int)($_POST['id'] ?? 0);
        $acao = (string)($_POST['acao'] ?? '');
        try {
            $codigo = match ($acao) {
                'confirmar' => confirmar_reserva($id),
                'cancelar'  => cancelar_reserva_pelo_hotel($id),
                default     => throw new RuntimeException('Ação desconhecida.'),
            };
            registar_auditoria('reserva_' . $acao, $codigo);
            flash('ok', $acao === 'confirmar'
                ? "Reserva $codigo confirmada."
                : "Reserva $codigo cancelada. Reembolso de 100% a processar.");
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
        }
    }
    $qs = http_build_query(array_filter(['estado' => $estado, 'q' => $q, 'pagina' => $pagina > 1 ? $pagina : '']));
    redirecionar(BASE_PATH . '/admin/reservas.php' . ($qs ? '?' . $qs : ''));
}

// ===== 3. Listagem =====
$where  = [];
$params = [];
if ($estado !== '') {
    $where[] = 'r.estado = :estado';
    $params[':estado'] = $estado;
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    if ($ver_contactos) {
        $where[] = '(r.codigo LIKE :q1 OR r.nome_cliente LIKE :q2 OR r.email LIKE :q3 OR r.telefone LIKE :q4)';
        $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
    } else {
        $where[] = 'r.codigo LIKE :q1';
        $params[':q1'] = $like;
    }
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = conectar()->prepare("SELECT COUNT(*) FROM reservas r $sql_where");
$st->execute($params);
$total = (int)$st->fetchColumn();

$por_pagina = 15;
$paginas    = max(1, (int)ceil($total / $por_pagina));
$pagina     = min($pagina, $paginas);
$offset     = ($pagina - 1) * $por_pagina;

$st = conectar()->prepare(
    "SELECT r.*, s.nome AS servico
     FROM reservas r JOIN servicos s ON s.id = r.servico_id
     $sql_where
     ORDER BY r.criado_em DESC
     LIMIT $por_pagina OFFSET $offset"
);
$st->execute($params);
$reservas = $st->fetchAll();

$tabs = ['' => 'Todas', 'pendente' => 'Pendentes', 'confirmada' => 'Confirmadas',
         'cancelada' => 'Canceladas', 'concluida' => 'Concluídas'];
$base = array_filter(['estado' => $estado, 'q' => $q]);

admin_topo('Reservas', 'reservas');
?>
<div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <nav class="flex flex-wrap gap-1.5">
        <?php foreach ($tabs as $chave => $rotulo): ?>
            <a href="?<?= e(http_build_query(array_filter(['estado' => $chave, 'q' => $q]))) ?>"
               class="rounded-full px-3.5 py-1.5 text-sm font-medium transition <?= $estado === $chave ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' ?>">
                <?= e($rotulo) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="flex gap-2">
        <?php if ($estado !== ''): ?><input type="hidden" name="estado" value="<?= e($estado) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" maxlength="50"
               placeholder="<?= $ver_contactos ? 'Código, nome, email ou telefone' : 'Código da reserva' ?>"
               class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30 lg:w-72">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Pesquisar</button>
    </form>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Reserva</th>
                    <th class="px-4 py-3">Serviço</th>
                    <th class="px-4 py-3">Datas</th>
                    <?php if ($ver_contactos): ?><th class="px-4 py-3">Cliente</th><?php endif; ?>
                    <?php if ($ver_valores): ?><th class="px-4 py-3">Valor</th><?php endif; ?>
                    <th class="px-4 py-3">Estado</th>
                    <?php if ($gerir): ?><th class="px-4 py-3 text-right">Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (!$reservas): ?>
                <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">Nenhuma reserva encontrada.</td></tr>
            <?php endif; ?>

            <?php foreach ($reservas as $r): ?>
                <tr class="hover:bg-slate-50/70">
                    <td class="px-4 py-3">
                        <p class="font-mono text-xs font-medium text-slate-800"><?= e($r['codigo']) ?></p>
                        <p class="text-xs text-slate-400"><?= e(fmt_data($r['criado_em'])) ?></p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900"><?= e($r['servico']) ?></p>
                        <p class="text-xs text-slate-500"><?= (int)$r['num_pessoas'] ?> pessoa(s)</p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                        <?= e(fmt_data($r['data_inicio'])) ?>
                        <?= $r['data_fim'] !== $r['data_inicio'] ? '<span class="text-slate-400">→</span> ' . e(fmt_data($r['data_fim'])) : '' ?>
                    </td>
                    <?php if ($ver_contactos): ?>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900"><?= e($r['nome_cliente']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($r['telefone']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($r['email']) ?></p>
                        </td>
                    <?php endif; ?>
                    <?php if ($ver_valores): ?>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-800"><?= e(formatar_mt($r['preco_total'])) ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-3">
                        <?= badge_estado($r['estado']) ?>
                        <?php if ($r['estado'] === 'cancelada'): ?>
                            <p class="mt-1 text-xs text-slate-500">
                                por <?= $r['cancelado_por'] === 'hotel' ? 'o hotel' : 'o cliente' ?>
                                <?= $ver_valores && $r['valor_reembolso'] !== null
                                    ? '· reembolso ' . (int)$r['reembolso_percentagem'] . '% (' . e(formatar_mt($r['valor_reembolso'])) . ')'
                                    : '' ?>
                            </p>
                        <?php endif; ?>
                    </td>
                    <?php if ($gerir): ?>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <?php if ($r['estado'] === 'pendente'): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="acao" value="confirmar">
                                    <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Confirmar</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($r['estado'], ['pendente', 'confirmada'], true)): ?>
                                <form method="post" class="ml-1 inline"
                                      data-confirmar="<?= e('Cancelar a reserva ' . $r['codigo'] . '? O cliente receberá reembolso de 100%.') ?>">
                                    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="acao" value="cancelar">
                                    <button class="rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <p><?= $total ?> reserva(s) · página <?= $pagina ?> de <?= $paginas ?></p>
        <div class="flex gap-2">
            <?php if ($pagina > 1): ?>
                <a href="?<?= e(http_build_query($base + ['pagina' => $pagina - 1])) ?>" class="rounded-md bg-white px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100">← Anterior</a>
            <?php endif; ?>
            <?php if ($pagina < $paginas): ?>
                <a href="?<?= e(http_build_query($base + ['pagina' => $pagina + 1])) ?>" class="rounded-md bg-white px-3 py-1.5 ring-1 ring-slate-200 hover:bg-slate-100">Seguinte →</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php admin_fundo(); ?>