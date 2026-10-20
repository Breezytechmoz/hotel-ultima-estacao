<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';

// Só termina a sessão com POST + token CSRF: um link malicioso não consegue "expulsar" o utilizador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validar_csrf($_POST['csrf'] ?? null)) {
    registar_auditoria('logout');
    terminar_sessao();
}
redirecionar(BASE_PATH . '/login.php');