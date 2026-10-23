<?php
function codigo_da_reserva(int $id): string
{
    $st = conectar()->prepare('SELECT codigo FROM reservas WHERE id = :id');
    $st->execute([':id' => $id]);
    return (string)$st->fetchColumn();
}

function confirmar_reserva(int $id): string
{
    // UPDATE condicional: só muda se ainda estiver pendente (seguro contra cliques duplos)
    $st = conectar()->prepare(
        "UPDATE reservas SET estado = 'confirmada', confirmado_em = NOW()
         WHERE id = :id AND estado = 'pendente'"
    );
    $st->execute([':id' => $id]);

    if ($st->rowCount() !== 1) {
        throw new RuntimeException('Esta reserva já não está pendente.');
    }
    return codigo_da_reserva($id);
}

function cancelar_reserva_pelo_hotel(int $id): string
{
    // Pendentes podem sempre ser canceladas; confirmadas só enquanto a estadia não terminou
    $st = conectar()->prepare(
        "UPDATE reservas
         SET estado = 'cancelada', cancelado_em = NOW(), cancelado_por = 'hotel',
             reembolso_percentagem = 100, valor_reembolso = preco_total
         WHERE id = :id
           AND (estado = 'pendente' OR (estado = 'confirmada' AND data_fim >= CURDATE()))"
    );
    $st->execute([':id' => $id]);

    if ($st->rowCount() !== 1) {
        throw new RuntimeException('Esta reserva já não pode ser cancelada (já terminou ou mudou de estado).');
    }
    return codigo_da_reserva($id);
}

function concluir_reserva(int $id): string
{
    $st = conectar()->prepare(
        "UPDATE reservas SET estado = 'concluida'
         WHERE id = :id AND estado = 'confirmada' AND data_fim < CURDATE()"
    );
    $st->execute([':id' => $id]);

    if ($st->rowCount() !== 1) {
        throw new RuntimeException('Só é possível concluir reservas confirmadas cuja data já terminou.');
    }
    return codigo_da_reserva($id);
}