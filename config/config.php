<?php
// ===== 1. Dados de ligação à base de dados =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel_ultima_estacao');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP: root sem palavra-passe por defeito

// ===== 2. Definições gerais da aplicação =====
define('BASE_URL', 'http://localhost/hotel-ultima-estacao');
define('BASE_PATH', '/hotel-ultima-estacao');   // em produção, na raiz do domínio: ''
date_default_timezone_set('Africa/Maputo');

// ===== 3. Função que devolve a ligação à base de dados =====
function conectar(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

// ===== 4. Função que lê todas as configurações do site =====
function obter_configuracoes(): array
{
    $linhas = conectar()->query('SELECT chave, valor FROM configuracoes')->fetchAll();

    $config = [];
    foreach ($linhas as $linha) {
        $config[$linha['chave']] = $linha['valor'];
    }
    return $config;
}