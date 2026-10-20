<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$u = exigir_permissao('configuracoes.editar');

$campos = [
    'nome_estabelecimento' => ['rotulo' => 'Nome do estabelecimento', 'tipo' => 'text',     'max' => 100],
    'descricao'            => ['rotulo' => 'Descrição',               'tipo' => 'textarea', 'max' => 500],
    'telefone'             => ['rotulo' => 'Telefone',                'tipo' => 'text',     'max' => 30],
    'email'                => ['rotulo' => 'Email',                   'tipo' => 'email',    'max' => 100],
    'endereco'             => ['rotulo' => 'Endereço',                'tipo' => 'text',     'max' => 200],
    'regras_gerais'        => ['rotulo' => 'Regras gerais da casa',   'tipo' => 'textarea', 'max' => 2000],
    'termos_condicoes'     => ['rotulo' => 'Termos e condições',      'tipo' => 'textarea', 'max' => 8000],
];

$erros = [];
$novos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';
    } else {
        foreach ($campos as $chave => $def) {
            $valor = trim((string)($_POST[$chave] ?? ''));
            if ($chave === 'nome_estabelecimento' && $valor === '') {
                $erros[] = 'O nome do estabelecimento é obrigatório.';
            }
            if (mb_strlen($valor) > $def['max']) {
                $erros[] = $def['rotulo'] . ' ultrapassa ' . $def['max'] . ' caracteres.';
            }
            $novos[$chave] = $valor;
        }
        if (!empty($novos['email']) && !filter_var($novos['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O email introduzido não é válido.';
        }

        if (empty($erros)) {
            $antigos    = obter_configuracoes();
            $alterados  = [];
            $pdo        = conectar();
            $pdo->beginTransaction();
            try {
                foreach ($novos as $chave => $valor) {
                    if (($antigos[$chave] ?? '') !== $valor) {
                        $alterados[] = $chave;
                    }
                    guardar_configuracao($chave, $valor);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            registar_auditoria('configuracoes_alteradas', $alterados ? implode(', ', $alterados) : 'sem alterações');
            flash('ok', 'Configurações guardadas com sucesso.');
            redirecionar(BASE_PATH . '/admin/configuracoes.php');
        }
    }
}

$valores = !empty($novos) ? $novos : obter_configuracoes();
admin_topo('Configurações', 'configuracoes');
?>
<?php if ($erros): ?>
    <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
        <ul class="list-disc space-y-1 pl-5">
            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="max-w-3xl space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">

    <?php foreach ($campos as $chave => $def): ?>
        <div>
            <label for="<?= e($chave) ?>" class="mb-1.5 block text-sm font-medium text-slate-700"><?= e($def['rotulo']) ?></label>
            <?php if ($def['tipo'] === 'textarea'): ?>
                <textarea id="<?= e($chave) ?>" name="<?= e($chave) ?>" rows="<?= $def['max'] > 1000 ? 7 : 3 ?>" maxlength="<?= (int)$def['max'] ?>"
                          class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30"><?= e($valores[$chave] ?? '') ?></textarea>
            <?php else: ?>
                <input type="<?= e($def['tipo']) ?>" id="<?= e($chave) ?>" name="<?= e($chave) ?>"
                       value="<?= e($valores[$chave] ?? '') ?>" maxlength="<?= (int)$def['max'] ?>"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="flex justify-end border-t border-slate-100 pt-5">
        <button class="rounded-lg bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
            Guardar alterações
        </button>
    </div>
</form>
<?php admin_fundo(); ?>