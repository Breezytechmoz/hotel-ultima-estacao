<?php
// ===== 1. Constantes =====
const SESSAO_INATIVIDADE  = 1800;    // 30 min sem atividade
const SESSAO_MAXIMA       = 28800;   // 8 h no total
const LOGIN_MAX_FALHAS    = 5;       // por email
const LOGIN_MAX_FALHAS_IP = 20;      // por IP
const LOGIN_JANELA_MIN    = 15;      // minutos
const PASSWORD_MIN        = 12;
const PASSWORD_MAX        = 128;

const NOMES_PAPEIS = [
    'super_admin' => 'Super Admin',
    'gestor'      => 'Gestor',
    'financeiro'  => 'Financeiro',
    'cliente'     => 'Cliente',
];

const PERMISSOES = [
    'super_admin' => ['*'],
    'gestor'      => ['painel.ver', 'reservas.ver', 'reservas.gerir', 'clientes.contactos',
                      'financeiro.ver', 'servicos.gerir', 'configuracoes.editar'],
    'financeiro'  => ['painel.ver', 'reservas.ver', 'financeiro.ver'],
    'cliente'     => [],
];

// ===== 2. Utilitários =====
function ip_cliente(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function redirecionar(string $url)
{
    header('Location: ' . $url);
    exit;
}

function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function obter_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ===== 3. Cabeçalhos de segurança =====
function enviar_cabecalhos_seguranca(bool $csp_estrito = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if ($csp_estrito) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
             . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }
}

// ===== 4. Sessão segura =====
function iniciar_sessao_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('HUE_SESSAO');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_PATH === '' ? '' : BASE_PATH) . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (isset($_SESSION['uid'])) {
        $agora    = time();
        $inativo  = $agora - (int)($_SESSION['ultima_atividade'] ?? 0);
        $duracao  = $agora - (int)($_SESSION['inicio'] ?? 0);

        if ($inativo > SESSAO_INATIVIDADE || $duracao > SESSAO_MAXIMA) {
            terminar_sessao();
            $_SESSION['aviso_login'] = 'A sua sessão expirou. Entre novamente.';
        } else {
            $_SESSION['ultima_atividade'] = $agora;
        }
    }
}

function terminar_sessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
    session_start();               // sessão nova e limpa (para mensagens e CSRF)
    session_regenerate_id(true);
}

// ===== 5. Palavras-passe =====
function algoritmo_password()
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function validar_nova_password(string $p): array
{
    $erros = [];
    if (mb_strlen($p) < PASSWORD_MIN) {
        $erros[] = 'A palavra-passe deve ter pelo menos ' . PASSWORD_MIN . ' caracteres.';
    }
    if (mb_strlen($p) > PASSWORD_MAX) {
        $erros[] = 'A palavra-passe deve ter no máximo ' . PASSWORD_MAX . ' caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
        $erros[] = 'Use letras e números.';
    }
    return $erros;
}

// ===== 6. Auditoria =====
function registar_auditoria(string $acao, ?string $detalhes = null, ?int $uid = null): void
{
    try {
        $st = conectar()->prepare(
            'INSERT INTO auditoria (utilizador_id, acao, detalhes, ip)
             VALUES (:u, :a, :d, :ip)'
        );
        $st->execute([
            ':u'  => $uid ?? ($_SESSION['uid'] ?? null),
            ':a'  => $acao,
            ':d'  => $detalhes !== null ? mb_substr($detalhes, 0, 500) : null,
            ':ip' => ip_cliente(),
        ]);
    } catch (Throwable $e) {
        error_log('Falha na auditoria: ' . $e->getMessage());   // nunca derruba a página
    }
}

// ===== 7. Utilizador atual e permissões =====
function utilizador_atual(): ?array
{
    static $carregado = false, $utilizador = null;

    if (!$carregado) {
        $carregado = true;
        if (!empty($_SESSION['uid'])) {
            $st = conectar()->prepare(
                'SELECT id, nome, email, papel, deve_mudar_password
                 FROM utilizadores WHERE id = :id AND ativo = 1'
            );
            $st->execute([':id' => (int)$_SESSION['uid']]);
            $utilizador = $st->fetch() ?: null;

            if (!$utilizador) {          // conta apagada ou desativada: sessão inválida
                terminar_sessao();
            }
        }
    }
    return $utilizador;
}

function pode(string $permissao): bool
{
    $u = utilizador_atual();
    if (!$u) {
        return false;
    }
    $lista = PERMISSOES[$u['papel']] ?? [];
    return in_array('*', $lista, true) || in_array($permissao, $lista, true);
}

function pagina_inicial_do_papel(string $papel): string
{
    return in_array($papel, ['super_admin', 'gestor', 'financeiro'], true)
        ? BASE_PATH . '/admin/index.php'
        : BASE_PATH . '/index.php';
}

// ===== 8. Guardas (usar no topo de cada página protegida) =====
function exigir_login(bool $permitir_troca_password = false): array
{
    enviar_cabecalhos_seguranca(true);

    $u = utilizador_atual();
    if (!$u) {
        redirecionar(BASE_PATH . '/login.php');
    }
    if ($u['deve_mudar_password'] && !$permitir_troca_password) {
        redirecionar(BASE_PATH . '/mudar_password.php');
    }
    return $u;
}

function exigir_permissao(string $permissao): array
{
    $u = exigir_login();

    if (!pode($permissao)) {
        registar_auditoria('acesso_negado', $permissao . ' em ' . ($_SERVER['SCRIPT_NAME'] ?? ''));
        http_response_code(403);
        require __DIR__ . '/erro_403.php';
        exit;
    }
    return $u;
}

// ===== 9. Login com limite de tentativas =====
function login_bloqueado(string $email, string $ip): bool
{
    $j   = (int)LOGIN_JANELA_MIN;
    $pdo = conectar();

    $st = $pdo->prepare("SELECT COUNT(*) FROM tentativas_login
                         WHERE email = :e AND criado_em > NOW() - INTERVAL $j MINUTE");
    $st->execute([':e' => $email]);
    if ((int)$st->fetchColumn() >= LOGIN_MAX_FALHAS) {
        return true;
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM tentativas_login
                         WHERE ip = :ip AND criado_em > NOW() - INTERVAL $j MINUTE");
    $st->execute([':ip' => $ip]);
    return (int)$st->fetchColumn() >= LOGIN_MAX_FALHAS_IP;
}

function registar_falha_login(string $email, string $ip): void
{
    $st = conectar()->prepare('INSERT INTO tentativas_login (email, ip) VALUES (:e, :ip)');
    $st->execute([':e' => mb_substr($email, 0, 100), ':ip' => $ip]);
}

function limpar_falhas_login(string $email): void
{
    $pdo = conectar();
    $st  = $pdo->prepare('DELETE FROM tentativas_login WHERE email = :e');
    $st->execute([':e' => $email]);
    $pdo->exec('DELETE FROM tentativas_login WHERE criado_em < NOW() - INTERVAL 1 DAY');
}

function tentar_login(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $ip    = ip_cliente();

    if ($email === '' || $password === '' || mb_strlen($password) > PASSWORD_MAX) {
        return ['ok' => false, 'erro' => 'Indique o email e a palavra-passe.'];
    }

    if (login_bloqueado($email, $ip)) {
        registar_auditoria('login_bloqueado', $email);
        return ['ok' => false,
                'erro' => 'Demasiadas tentativas. Aguarde ' . LOGIN_JANELA_MIN . ' minutos e tente novamente.'];
    }

    $st = conectar()->prepare(
        'SELECT id, password_hash, papel FROM utilizadores WHERE email = :e AND ativo = 1'
    );
    $st->execute([':e' => $email]);
    $u = $st->fetch();

    if ($u) {
        $valida = password_verify($password, $u['password_hash']);
    } else {
        password_hash($password, algoritmo_password());   // gasta o mesmo tempo que uma verificação real
        $valida = false;
    }

    if (!$valida) {
        registar_falha_login($email, $ip);
        registar_auditoria('login_falhou', $email);
        return ['ok' => false, 'erro' => 'Email ou palavra-passe incorretos.'];
    }

    // Sucesso
    limpar_falhas_login($email);

    if (password_needs_rehash($u['password_hash'], algoritmo_password())) {
        $novo = password_hash($password, algoritmo_password());
        conectar()->prepare('UPDATE utilizadores SET password_hash = :h WHERE id = :id')
                  ->execute([':h' => $novo, ':id' => $u['id']]);
    }

    session_regenerate_id(true);            // novo ID: impede "fixação de sessão"
    unset($_SESSION['csrf']);               // novo token CSRF
    $_SESSION['uid']              = (int)$u['id'];
    $_SESSION['inicio']           = time();
    $_SESSION['ultima_atividade'] = time();

    conectar()->prepare('UPDATE utilizadores SET ultimo_login_em = NOW() WHERE id = :id')
              ->execute([':id' => $u['id']]);
    registar_auditoria('login_ok', $email, (int)$u['id']);

    return ['ok' => true, 'papel' => $u['papel']];
}