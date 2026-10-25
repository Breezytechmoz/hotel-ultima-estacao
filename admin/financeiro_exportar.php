<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/financeiro_funcoes.php';

$u = exigir_permissao('financeiro.ver');

$mes    = ler_mes($_GET['mes'] ?? null);
$linhas = reservas_do_mes_para_exportar($mes, $mes->modify('+1 month'));

registar_auditoria('relatorio_exportado', $mes->format('Y-m') . ' (' . count($linhas) . ' linhas)');

$dinheiro = fn($v) => number_format((float)$v, 2, ',', '');
$data     = fn($d) => (new DateTimeImmutable($d))->format('d/m/Y');

// Escreve o CSV (em UTF-8) numa memória temporária
$mem = fopen('php://temp', 'w+');
fputcsv($mem, ['Código', 'Serviço', 'Tipo', 'Início', 'Fim', 'Estado',
               'Valor (MT)', 'Reembolso (%)', 'Reembolso (MT)', 'Cancelado por'], ';', '"', '\\');

foreach ($linhas as $l) {
    fputcsv($mem, [
        csv_seguro($l['codigo']),
        csv_seguro($l['servico']),
        $l['tipo'],
        $data($l['data_inicio']),
        $data($l['data_fim']),
        $l['estado'],
        $dinheiro($l['preco_total']),
        $l['reembolso_percentagem'] ?? '',
        $l['valor_reembolso'] !== null ? $dinheiro($l['valor_reembolso']) : '',
        $l['cancelado_por'] ?? '',
    ], ';', '"', '\\');
}
rewind($mem);
$conteudo = stream_get_contents($mem);
fclose($mem);

// O Excel antigo não reconhece UTF-8 em CSV: converte para Windows-1252
$conteudo = mb_convert_encoding($conteudo, 'Windows-1252', 'UTF-8');

header('Content-Type: text/csv; charset=windows-1252');
header('Content-Disposition: attachment; filename="relatorio-' . $mes->format('Y-m') . '.csv"');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($conteudo));
echo $conteudo;