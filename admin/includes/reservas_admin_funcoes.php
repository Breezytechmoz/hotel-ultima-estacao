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
    // Cancelamento pelo hotel: reembolso integral (100%)
    $st = conectar()->prepare(
        "UPDATE reservas
         SET estado = 'cancelada', cancelado_em = NOW(), cancelado_por = 'hotel',
             reembolso_percentagem = 100, valor_reembolso = preco_total
         WHERE id = :id AND estado IN ('pendente', 'confirmada')"
    );
    $st->execute([':id' => $id]);

    if ($st->rowCount() !== 1) {
        throw new RuntimeException('Esta reserva já não pode ser cancelada.');
    }
    return codigo_da_reserva($id);
}