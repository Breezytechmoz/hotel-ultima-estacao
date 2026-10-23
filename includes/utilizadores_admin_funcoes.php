<?php
// Papéis que podem ser atribuídos pelo painel (nunca super_admin)
const PAPEIS_ATRIBUIVEIS = [
    'gestor'     => 'Gestor',
    'financeiro' => 'Financeiro',
    'cliente'    => 'Cliente',
];

function badge_papel(string $papel): string
{
    $mapa = [
        'super_admin' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'gestor'      => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'financeiro'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'cliente'     => 'bg-slate-50 text-slate-600 ring-slate-500/20',
    ];
    return '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '
         . ($mapa[$papel] ?? $mapa['cliente']) . '">' . e(NOMES_PAPEIS[$papel] ?? $papel) . '</span>';
}

function listar_utilizadores(): array
{
    return conectar()->query(
        'SELECT id, nome, email, papel, ativo, deve_mudar_password, ultimo_login_em
         FROM utilizadores ORDER BY ativo DESC, papel, nome'
    )->fetchAll();
}

function obter_utilizador(int $id): ?array
{
    $st = conectar()->prepare(
        'SELECT id, nome, email, papel, ativo FROM utilizadores WHERE id = :id'
    );
    $st->execute([':id' => $id]);
    return $st->fetch() ?: null;
}

// Regras: não se gere a si próprio nem contas de Super Admin
function garantir_gestao_permitida(?array $alvo, int $meu_id): array
{
    if (!$alvo) {
        throw new RuntimeException('Utilizador não encontrado.');
    }
    if ($alvo['papel'] === 'super_admin') {
        throw new RuntimeException('As contas de Super Admin só se gerem pelo terminal.');
    }
    if ((int)$alvo['id'] === $meu_id) {
        throw new RuntimeException('Não pode alterar a sua própria conta aqui. Use «Alterar palavra-passe».');
    }
    return $alvo;
}

function validar_dados_utilizador(array $p, bool $novo): array
{
    $erros = [];
    $nome  = trim((string)($p['nome'] ?? ''));
    $email = mb_strtolower(trim((string)($p['email'] ?? '')));
    $papel = (string)($p['papel'] ?? '');

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
        $erros[] = 'O nome deve ter entre 3 e 100 caracteres.';
    }
    if ($novo && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100)) {
        $erros[] = 'O email não é válido.';
    }
    if (!isset(PAPEIS_ATRIBUIVEIS[$papel])) {
        $erros[] = 'Escolha um papel válido.';
    }
    return [$erros, $nome, $email, $papel];
}

function criar_utilizador_web(string $nome, string $email, string $papel): array
{
    $senha = nova_password_temporaria();
    $pdo   = conectar();

    try {
        $pdo->prepare(
            'INSERT INTO utilizadores (nome, email, password_hash, papel, deve_mudar_password)
             VALUES (:n, :e, :h, :p, 1)'
        )->execute([
            ':n' => $nome, ':e' => $email,
            ':h' => password_hash($senha, algoritmo_password()), ':p' => $papel,
        ]);
        return [(int)$pdo->lastInsertId(), $senha];

    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            throw new RuntimeException('Já existe um utilizador com este email.');
        }
        throw $ex;
    }
}

function atualizar_utilizador(int $id, string $nome, string $papel): void
{
    conectar()->prepare('UPDATE utilizadores SET nome = :n, papel = :p WHERE id = :id')
              ->execute([':n' => $nome, ':p' => $papel, ':id' => $id]);
}

// Estado explícito + nova versão de sessão: reativar nunca "ressuscita" sessões antigas
function definir_estado_utilizador(int $id, bool $ativo): void
{
    conectar()->prepare(
        'UPDATE utilizadores SET ativo = :a, versao_sessao = versao_sessao + 1 WHERE id = :id'
    )->execute([':a' => $ativo ? 1 : 0, ':id' => $id]);
}

function repor_password_utilizador(int $id): string
{
    $senha = nova_password_temporaria();
    conectar()->prepare(
        'UPDATE utilizadores
         SET password_hash = :h, deve_mudar_password = 1, versao_sessao = versao_sessao + 1
         WHERE id = :id'
    )->execute([':h' => password_hash($senha, algoritmo_password()), ':id' => $id]);
    return $senha;
}