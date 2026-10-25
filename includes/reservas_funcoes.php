<?php
// ===== 1. Converter texto "2026-10-25" em data, validando de verdade =====
function parse_data(string $texto): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
    return ($d && $d->format('Y-m-d') === $texto) ? $d : null;
}

// ===== 2. Formatar dinheiro =====
function formatar_mt(float|string $valor): string
{
    return number_format((float)$valor, 2, ',', ' ') . ' MT';
}

// ===== 3. Quantas unidades cobrar (noites, dias ou 1 evento) =====
function calcular_unidades(string $unidade, DateTimeImmutable $ini, DateTimeImmutable $fim): int
{
    $dias = (int)$ini->diff($fim)->days;

    return match ($unidade) {
        'noite' => $dias,
        'dia'   => $dias + 1,
        default => 1,
    };
}

// ===== 4. Código único da reserva =====
function gerar_codigo_reserva(): string
{
    return 'HUE-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

// ===== 5. Listar serviços ativos, já com as comodidades =====
function listar_servicos(): array
{
    $sql = "SELECT s.*,
                   GROUP_CONCAT(c.nome ORDER BY c.nome SEPARATOR '|') AS comodidades
            FROM servicos s
            LEFT JOIN servico_comodidade sc ON sc.servico_id = s.id
            LEFT JOIN comodidades c         ON c.id = sc.comodidade_id
            WHERE s.ativo = 1
            GROUP BY s.id
            ORDER BY s.tipo, s.preco";

    $resultado = [];
    foreach (conectar()->query($sql)->fetchAll() as $linha) {
        $linha['lista_comodidades'] = $linha['comodidades']
            ? explode('|', $linha['comodidades'])
            : [];
        $resultado[(int)$linha['id']] = $linha;
    }
    return $resultado;
}

// ===== 6. Criar a reserva (verifica disponibilidade e grava) =====
function criar_reserva(int $servicoId, array $d, DateTimeImmutable $ini, DateTimeImmutable $fim): string
{
    $pdo = conectar();
    $pdo->beginTransaction();

    try {
        // Trava a linha do serviço: dois clientes em simultâneo ficam em fila
        $stmt = $pdo->prepare(
            'SELECT preco, unidade, quantidade
             FROM servicos WHERE id = :id AND ativo = 1 FOR UPDATE'
        );
        $stmt->execute([':id' => $servicoId]);
        $servico = $stmt->fetch();

        if (!$servico) {
            throw new RuntimeException('Este serviço já não está disponível.');
        }

        // Quartos: o dia de saída fica livre. Viaturas: o dia de devolução conta.
        [$opIni, $opFim] = $servico['unidade'] === 'noite' ? ['<', '>'] : ['<=', '>='];

        $sql = "SELECT COUNT(*) FROM reservas
                WHERE servico_id = :id
                  AND estado <> 'cancelada'
                  AND data_inicio $opIni :fim
                  AND data_fim    $opFim :ini";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'  => $servicoId,
            ':ini' => $ini->format('Y-m-d'),
            ':fim' => $fim->format('Y-m-d'),
        ]);

        if ((int)$stmt->fetchColumn() >= (int)$servico['quantidade']) {
            throw new RuntimeException('Sem disponibilidade nas datas escolhidas. Tente outras datas.');
        }

        $unidades = calcular_unidades($servico['unidade'], $ini, $fim);
        $total    = $unidades * (float)$servico['preco'];
        $codigo   = gerar_codigo_reserva();

       $stmt = $pdo->prepare(
            'INSERT INTO reservas
            (codigo, servico_id, nome_cliente, email, telefone,
            data_inicio, data_fim, num_pessoas, observacoes, preco_total, aceitou_termos_em)
            VALUES
            (:codigo, :servico, :nome, :email, :tel,
            :ini, :fim, :pessoas, :obs, :total, NOW())'
        );
        $stmt->execute([
            ':codigo'  => $codigo,
            ':servico' => $servicoId,
            ':nome'    => $d['nome'],
            ':email'   => $d['email'],
            ':tel'     => $d['telefone'],
            ':ini'     => $ini->format('Y-m-d'),
            ':fim'     => $fim->format('Y-m-d'),
            ':pessoas' => $d['num_pessoas'],
            ':obs'     => $d['observacoes'] !== '' ? $d['observacoes'] : null,
            ':total'   => $total,
        ]);

        $pdo->commit();
        return $codigo;

    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}