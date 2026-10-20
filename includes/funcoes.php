<?php
// ===== 1. Segurança e sessão =====
require_once __DIR__ . '/seguranca.php';

enviar_cabecalhos_seguranca();
iniciar_sessao_segura();

// ===== 2. Função "e": escapar texto antes de o mostrar no HTML =====
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

// ===== 3. Proteção CSRF =====
function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validar_csrf(?string $token): bool
{
    return isset($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

// ===== 4. Gravar uma configuração =====
function guardar_configuracao(string $chave, string $valor): void
{
    $sql = 'INSERT INTO configuracoes (chave, valor)
            VALUES (:chave, :valor)
            ON DUPLICATE KEY UPDATE valor = :valor2';

    $stmt = conectar()->prepare($sql);
    $stmt->execute([
        ':chave'  => $chave,
        ':valor'  => $valor,
        ':valor2' => $valor,
    ]);
}