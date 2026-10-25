<?php
// ===== 1. Ler todas as regras, agrupadas por tipo =====
function listar_regras_cancelamento(): array
{
    $sql = 'SELECT tipo, horas_antecedencia_min, percentagem_reembolso
            FROM politica_cancelamento
            ORDER BY tipo, horas_antecedencia_min DESC';

    $por_tipo = [];
    foreach (conectar()->query($sql)->fetchAll() as $linha) {
        $por_tipo[$linha['tipo']][] = $linha;
    }
    return $por_tipo;
}

// ===== 2. "168" -> "7 dia(s)" ; "36" -> "36 horas" =====
function formatar_antecedencia(int $horas): string
{
    return ($horas % 24 === 0) ? ($horas / 24) . ' dia(s)' : $horas . ' horas';
}

// ===== 3. Calcular o reembolso de uma reserva =====
function calcular_reembolso(array $reserva, ?DateTimeImmutable $agora = null): array
{
    $agora  = $agora ?? new DateTimeImmutable('now');
    $inicio = new DateTimeImmutable($reserva['data_inicio'] . ' 00:00:00');
    $horas  = ($inicio->getTimestamp() - $agora->getTimestamp()) / 3600;

    $regras = listar_regras_cancelamento()[$reserva['tipo']] ?? [];

    $percentagem = 0;
    foreach ($regras as $regra) {              // da maior antecedência para a menor
        if ($horas >= (int)$regra['horas_antecedencia_min']) {
            $percentagem = (int)$regra['percentagem_reembolso'];
            break;
        }
    }

    return [
        'percentagem' => $percentagem,
        'valor'       => round((float)$reserva['preco_total'] * $percentagem / 100, 2),
        'horas'       => (int)floor($horas),
    ];
}

// ===== 4. Esta reserva pode ser cancelada? (devolve a mensagem de erro, ou null) =====
function validar_cancelavel(array $reserva): ?string
{
    if (!in_array($reserva['estado'], ['pendente', 'confirmada'], true)) {
        return 'Esta reserva já não pode ser cancelada (estado: ' . $reserva['estado'] . ').';
    }
    if ($reserva['data_inicio'] < date('Y-m-d')) {
        return 'Esta reserva já começou e não pode ser cancelada online.';
    }
    return null;
}

// ===== 5. Procurar reserva (código + email, para ninguém ver reservas alheias) =====
function buscar_reserva_cliente(string $codigo, string $email): ?array
{
    $stmt = conectar()->prepare(
        'SELECT r.*, s.nome AS servico_nome, s.tipo
         FROM reservas r
         JOIN servicos s ON s.id = r.servico_id
         WHERE r.codigo = :codigo AND r.email = :email'
    );
    $stmt->execute([':codigo' => $codigo, ':email' => $email]);
    return $stmt->fetch() ?: null;
}

// ===== 6. Cancelar de verdade =====
function cancelar_reserva(string $codigo, string $email): array
{
    $pdo = conectar();
    $pdo->beginTransaction();

    try {
        // Trava só a reserva (o subselect não bloqueia a tabela de serviços)
        $stmt = $pdo->prepare(
            'SELECT r.*, (SELECT tipo FROM servicos WHERE id = r.servico_id) AS tipo
             FROM reservas r
             WHERE r.codigo = :codigo AND r.email = :email
             FOR UPDATE'
        );
        $stmt->execute([':codigo' => $codigo, ':email' => $email]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            throw new RuntimeException('Reserva não encontrada. Verifique o código e o email.');
        }
        if ($erro = validar_cancelavel($reserva)) {
            throw new RuntimeException($erro);
        }

        $calculo = calcular_reembolso($reserva);

        $stmt = $pdo->prepare(
            "UPDATE reservas
                SET estado = 'cancelada', cancelado_em = NOW(), cancelado_por = 'cliente',
                    reembolso_percentagem = :pct, valor_reembolso = :valor
                WHERE id = :id"
        );
        $stmt->execute([
            ':pct'   => $calculo['percentagem'],
            ':valor' => $calculo['valor'],
            ':id'    => $reserva['id'],
        ]);

        $pdo->commit();
        return $calculo + ['codigo' => $codigo];

    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

    // ===== Limite de tentativas falhadas ao consultar uma reserva =====
const CONSULTA_MAX_FALHAS = 8;

function consulta_bloqueada(string $ip): bool
{
    $j  = (int)LOGIN_JANELA_MIN;
    $st = conectar()->prepare(
        "SELECT COUNT(*) FROM tentativas_consulta
         WHERE ip = :ip AND criado_em > NOW() - INTERVAL $j MINUTE"
    );
    $st->execute([':ip' => $ip]);
    return (int)$st->fetchColumn() >= CONSULTA_MAX_FALHAS;
}

function registar_falha_consulta(string $ip): void
{
    $pdo = conectar();
    $pdo->prepare('INSERT INTO tentativas_consulta (ip) VALUES (:ip)')->execute([':ip' => $ip]);
    $pdo->exec('DELETE FROM tentativas_consulta WHERE criado_em < NOW() - INTERVAL 1 DAY');
}
