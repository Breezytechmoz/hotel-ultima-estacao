<?php
const MESES_CURTOS = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
                      7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
const MESES_LONGOS = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                      7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];

// ===== 1. Ler o mês do endereço (qualquer valor estranho cai no mês atual) =====
function ler_mes(?string $texto): DateTimeImmutable
{
    if (is_string($texto) && preg_match('/^(\d{4})-(\d{2})$/', $texto, $m)
        && (int)$m[1] >= 2020 && (int)$m[1] <= 2100
        && (int)$m[2] >= 1 && (int)$m[2] <= 12) {
        return new DateTimeImmutable($m[1] . '-' . $m[2] . '-01');
    }
    return new DateTimeImmutable('first day of this month midnight');
}

// ===== 2. Resumo do mês =====
function resumo_do_mes(DateTimeImmutable $ini, DateTimeImmutable $fim): array
{
    $st = conectar()->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN estado IN ('confirmada','concluida') THEN 1 END), 0)             AS n_validas,
            COALESCE(SUM(CASE WHEN estado IN ('confirmada','concluida') THEN preco_total END), 0)   AS valor_validas,
            COALESCE(SUM(CASE WHEN estado = 'pendente' THEN 1 END), 0)                              AS n_pendentes,
            COALESCE(SUM(CASE WHEN estado = 'pendente' THEN preco_total END), 0)                    AS valor_pendente,
            COALESCE(SUM(CASE WHEN estado = 'cancelada' THEN 1 END), 0)                             AS n_canceladas,
            COALESCE(SUM(CASE WHEN estado = 'cancelada' THEN COALESCE(valor_reembolso, 0) END), 0)  AS reembolsado,
            COALESCE(SUM(CASE WHEN estado = 'cancelada'
                              THEN preco_total - COALESCE(valor_reembolso, 0) END), 0)              AS retido
         FROM reservas
         WHERE data_inicio >= :ini AND data_inicio < :fim"
    );
    $st->execute([':ini' => $ini->format('Y-m-d'), ':fim' => $fim->format('Y-m-d')]);
    return $st->fetch();
}

// ===== 3. Valor por serviço =====
function valor_por_servico(DateTimeImmutable $ini, DateTimeImmutable $fim): array
{
    $st = conectar()->prepare(
        "SELECT s.nome, s.tipo, COUNT(*) AS n, SUM(r.preco_total) AS valor
         FROM reservas r JOIN servicos s ON s.id = r.servico_id
         WHERE r.estado IN ('confirmada','concluida')
           AND r.data_inicio >= :ini AND r.data_inicio < :fim
         GROUP BY s.id, s.nome, s.tipo
         ORDER BY valor DESC"
    );
    $st->execute([':ini' => $ini->format('Y-m-d'), ':fim' => $fim->format('Y-m-d')]);
    return $st->fetchAll();
}

// ===== 4. Valor de cada mês do ano (para o gráfico) =====
function valor_por_mes_do_ano(int $ano): array
{
    $st = conectar()->prepare(
        "SELECT MONTH(data_inicio) AS m, SUM(preco_total) AS valor
         FROM reservas
         WHERE estado IN ('confirmada','concluida')
           AND data_inicio >= :ini AND data_inicio < :fim
         GROUP BY MONTH(data_inicio)"
    );
    $st->execute([':ini' => "$ano-01-01", ':fim' => ($ano + 1) . '-01-01']);

    $meses = array_fill(1, 12, 0.0);
    foreach ($st->fetchAll() as $linha) {
        $meses[(int)$linha['m']] = (float)$linha['valor'];
    }
    return $meses;
}

// ===== 5. Cancelamentos por origem =====
function cancelamentos_por_origem(DateTimeImmutable $ini, DateTimeImmutable $fim): array
{
    $st = conectar()->prepare(
        "SELECT cancelado_por, COUNT(*) AS n, SUM(preco_total) AS bruto,
                SUM(COALESCE(valor_reembolso, 0)) AS reembolso
         FROM reservas
         WHERE estado = 'cancelada' AND data_inicio >= :ini AND data_inicio < :fim
         GROUP BY cancelado_por"
    );
    $st->execute([':ini' => $ini->format('Y-m-d'), ':fim' => $fim->format('Y-m-d')]);
    return $st->fetchAll();
}

// ===== 6. Linhas para exportar (sem dados pessoais) =====
function reservas_do_mes_para_exportar(DateTimeImmutable $ini, DateTimeImmutable $fim): array
{
    $st = conectar()->prepare(
        "SELECT r.codigo, s.nome AS servico, s.tipo, r.data_inicio, r.data_fim, r.estado,
                r.preco_total, r.reembolso_percentagem, r.valor_reembolso, r.cancelado_por
         FROM reservas r JOIN servicos s ON s.id = r.servico_id
         WHERE r.data_inicio >= :ini AND r.data_inicio < :fim
         ORDER BY r.data_inicio, r.id"
    );
    $st->execute([':ini' => $ini->format('Y-m-d'), ':fim' => $fim->format('Y-m-d')]);
    return $st->fetchAll();
}

// ===== 7. Proteção contra "injeção de fórmulas" no Excel =====
function csv_seguro(string $valor): string
{
    return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
}