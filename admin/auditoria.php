<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$u = exigir_permissao('auditoria.ver');

function classe_acao(string $acao): string
{
    if (in_array($acao, ['login_falhou', 'login_bloqueado', 'acesso_negado'], true)) {
        return 'bg-rose-50 text-rose-700';
    }
    if (in_array($acao, ['login_ok', 'logout'], true)) {
        return 'bg-slate-100 text-slate-600';
    }
    if (str_starts_with($acao, 'utilizador_') || str_starts_with($acao, 'password_')) {
        return 'bg-amber-50 text-amber-700';
    }
    return 'bg-blue-50 text-blue-700';
}

// ===== Filtros (validados) =====
$acoes  = conectar()->query('SELECT DISTINCT acao FROM auditoria ORDER BY acao')->fetchAll(PDO::FETCH_COLUMN);
$acao   = in_array($_GET['acao'] ?? '', $acoes, true) ? $_GET['acao'] : '';
$q      = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$where  = [];
$params = [];
if ($acao !== '') {
    $where[] = 'a.acao = :acao';
    $params[':acao'] = $acao;
}
if ($q !== '') {
    $like    = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(a.detalhes LIKE :q1 OR u.email LIKE :q2 OR u.nome LIKE :q3 OR a.ip LIKE :q4)';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = conectar()->prepare("SELECT COUNT(*) FROM auditoria a LEFT JOIN utilizadores u ON u.id = a.utilizador_id $sql_where");
$st->execute($params);
$total = (int)$st->fetchColumn();

$por_pagina = 25;
$paginas    = max(1, (int)ceil($total / $por_pagina));
$pagina     = min($pagina, $paginas);
$offset     = ($pagina - 1) * $por_pagina;

$st = conectar()->prepare(
    "SELECT a.*, u.nome AS utilizador_nome, u.email AS utilizador_email
     FROM auditoria a LEFT JOIN utilizadores u ON u.id = a.utilizador_id
     $sql_where
     ORDER BY a.id DESC
     LIMIT $por_pagina OFFSET $offset"
);
$st->execute($params);
$registos = $st->fetchAll();

// ===== Alertas das últimas 24 horas =====
$alertas = conectar()->query(
    "SELECT COALESCE(SUM(acao = 'login_falhou'), 0)    AS falhas,
            COALESCE(SUM(acao = 'login_bloqueado'), 0) AS bloqueios,
            COALESCE(SUM(acao = 'acesso_negado'), 0)   AS negados
     FROM auditoria WHERE criado_em > NOW() - INTERVAL 1 DAY"
)->fetch();

$base = array_filter(['acao' => $acao, 'q' => $q]);

admin_topo('Auditoria', 'auditoria');
?>
<p class="mb-3 text-sm font-medium text-slate-600">Últimas 24 horas</p>
<div class="mb-8 grid gap-4 sm:grid-cols-3">
    <?= cartao_metrica('Logins falhados', (string)(int)$alertas['falhas'], 'palavra-passe ou email errados', 'auditoria', 'bg-amber-50 text-amber-600') ?>
    <?= cartao_metrica('Contas bloqueadas', (string)(int)$alertas['bloqueios'], 'por excesso de tentativas', 'auditoria', 'bg-rose-50 text-rose-600') ?>
    <?= cartao_metrica('Acessos negados', (string)(int)$alertas['negados'], 'tentativas sem permissão', 'auditoria', 'bg-rose-50 text-rose-600') ?>
</div>

<form method="get" class="mb-5 flex flex-wrap gap-2">
    <select name="acao" class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30">
        <option value="">Todas as ações</option>
        <?php foreach ($acoes as $a): ?>
            <option value="<?= e($a) ?>" <?= $acao === $a ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="60" placeholder="Pessoa, detalhe ou IP"
           class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30 sm:w-72">
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filtrar</button>
    <?php if ($base): ?>
        <a href="?" class="px-2 py-2 text-sm text-slate-500 hover:text-slate-800">Limpar</a>
    <?php endif; ?>
</form>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Quando</th>
                    <th class="px-4 py-3">Quem</th>
                    <th class="px-4 py-3">Ação</th>
                    <th class="px-4 py-3">Detalhes</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (!$registos): ?>
                <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">Nenhum registo encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($registos as $l): ?>
                <tr class="hover:bg-slate-50/70">
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= e(fmt_data_hora($l['criado_em'])) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($l['utilizador_nome']): ?>
                            <p class="font-medium text-slate-900"><?= e($l['utilizador_nome']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($l['utilizador_email']) ?></p>
                        <?php else: ?>
                            <span class="text-slate-400">Anónimo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-md px-2 py-0.5 font-mono text-xs <?= classe_acao($l['acao']) ?>"><?= e($l['acao']) ?></span>
                    </td>
                    <td class="max-w-md break-words px-4 py-3 text-slate-700"><?= e($l['detalhes'] ?? '') ?></td>
                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500"><?= e($l['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <p><?= $total ?> registo(s) · página <?= $pagina ?> de <?= $paginas ?></p>
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