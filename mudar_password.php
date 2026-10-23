<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';

$u     = exigir_login(true);          // true = permitido mesmo com troca obrigatória pendente
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atual     = (string)($_POST['atual'] ?? '');
    $nova      = (string)($_POST['nova'] ?? '');
    $confirmar = (string)($_POST['confirmar'] ?? '');
    $ip        = ip_cliente();

    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';
    } elseif (login_bloqueado($u['email'], $ip)) {
        $erros[] = 'Demasiadas tentativas. Aguarde ' . LOGIN_JANELA_MIN . ' minutos.';
    } else {
        $st = conectar()->prepare('SELECT password_hash FROM utilizadores WHERE id = :id');
        $st->execute([':id' => $u['id']]);
        $hash = (string)$st->fetchColumn();

        if (!password_verify($atual, $hash)) {
            registar_falha_login($u['email'], $ip);
            $erros[] = 'A palavra-passe atual está incorreta.';
        }
        $erros = array_merge($erros, validar_nova_password($nova));
        if ($nova !== $confirmar) {
            $erros[] = 'A confirmação não coincide com a nova palavra-passe.';
        }
        if ($nova === $atual) {
            $erros[] = 'A nova palavra-passe tem de ser diferente da atual.';
        }

        if (empty($erros)) {
            $pdo = conectar();
            $pdo->prepare(
                'UPDATE utilizadores
                SET password_hash = :h, deve_mudar_password = 0, versao_sessao = versao_sessao + 1
                WHERE id = :id'
            )->execute([':h' => password_hash($nova, algoritmo_password()), ':id' => $u['id']]);

            $st = $pdo->prepare('SELECT versao_sessao FROM utilizadores WHERE id = :id');
            $st->execute([':id' => $u['id']]);
            $_SESSION['sv'] = (int)$st->fetchColumn();     // esta sessão continua; as outras caem

            session_regenerate_id(true);
            registar_auditoria('password_alterada');
            flash('ok', 'Palavra-passe alterada com sucesso.');
            redirecionar(pagina_inicial_do_papel($u['papel']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Alterar palavra-passe</title>
    <?php require __DIR__ . '/includes/pwa_head.php'; ?>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-6">
<main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Alterar palavra-passe</h1>
    <p class="mt-1 text-sm text-slate-500">
        <?= $u['deve_mudar_password']
            ? 'Por segurança, defina uma palavra-passe só sua antes de continuar.'
            : 'Escolha uma palavra-passe nova.' ?>
    </p>

    <?php if ($erros): ?>
        <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <ul class="list-disc space-y-1 pl-5">
                <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="mt-6 space-y-5">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
        <?php
        $campos = [
            ['atual',     'Palavra-passe atual',            'current-password'],
            ['nova',      'Nova palavra-passe',             'new-password'],
            ['confirmar', 'Confirmar nova palavra-passe',   'new-password'],
        ];
        foreach ($campos as [$nome, $rotulo, $auto]): ?>
            <div>
                <label for="<?= $nome ?>" class="mb-1.5 block text-sm font-medium text-slate-700"><?= e($rotulo) ?></label>
                <input type="password" id="<?= $nome ?>" name="<?= $nome ?>" required autocomplete="<?= $auto ?>" maxlength="128"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600/30">
            </div>
        <?php endforeach; ?>
        <p class="text-xs text-slate-500">Mínimo de <?= PASSWORD_MIN ?> caracteres, com letras e números. Uma frase longa funciona bem.</p>

        <button class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
            Guardar nova palavra-passe
        </button>
    </form>

    <form method="post" action="<?= e(BASE_PATH) ?>/logout.php" class="mt-4 text-center">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">
        <button class="text-sm text-slate-500 hover:text-slate-800">Terminar sessão</button>
    </form>
</main>
</body>
</html>