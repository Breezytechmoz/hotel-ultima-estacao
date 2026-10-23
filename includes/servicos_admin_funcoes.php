<?php
const TIPOS_SERVICO = [
    'quarto'     => 'Alojamento',
    'rent_a_car' => 'Rent-a-car',
    'evento'     => 'Sala de eventos',
    'catering'   => 'Catering e ornamentação',
];

// A forma de cobrança decorre do tipo (a lógica de datas e de preços depende disto)
const UNIDADE_POR_TIPO = [
    'quarto'     => 'noite',
    'rent_a_car' => 'dia',
    'evento'     => 'evento',
    'catering'   => 'evento',
];

const ROTULO_UNIDADE = ['noite' => 'por noite', 'dia' => 'por dia', 'evento' => 'por evento'];

// ===== 1. Preço: aceita "2500", "2500,50" ou "2500.50" =====
function normalizar_preco(string $texto): ?string
{
    $t = str_ireplace([' ', "\u{00A0}", 'MT'], '', trim($texto));
    $t = str_replace(',', '.', $t);

    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $t) || (float)$t <= 0) {
        return null;
    }
    return number_format((float)$t, 2, '.', '');
}

// ===== 2. Ler e validar o formulário de um serviço =====
function ler_dados_servico(array $p, bool $novo): array
{
    $erros = [];
    $nome  = trim((string)($p['nome'] ?? ''));
    $desc  = trim((string)($p['descricao'] ?? ''));
    $tipo  = (string)($p['tipo'] ?? '');
    $preco = normalizar_preco((string)($p['preco'] ?? ''));
    $cap   = filter_var($p['capacidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    $qtd   = filter_var($p['quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
        $erros[] = 'O nome deve ter entre 3 e 100 caracteres.';
    }
    if (mb_strlen($desc) > 1000) {
        $erros[] = 'A descrição ultrapassa 1000 caracteres.';
    }
    if ($novo && !isset(TIPOS_SERVICO[$tipo])) {
        $erros[] = 'Escolha um tipo de serviço válido.';
    }
    if ($preco === null) {
        $erros[] = 'Preço inválido. Use só números, por exemplo 2500 ou 2500,50.';
    }
    if ($cap === false) {
        $erros[] = 'A capacidade deve ser um número entre 1 e 1000.';
    }
    if ($qtd === false) {
        $erros[] = 'A quantidade deve ser um número entre 1 e 500.';
    }

    return [
        'erros' => $erros,
        'dados' => [
            'nome' => $nome, 'descricao' => $desc, 'tipo' => $tipo, 'preco' => $preco,
            'capacidade' => $cap, 'quantidade' => $qtd, 'ativo' => !empty($p['ativo']) ? 1 : 0,
        ],
    ];
}

// ===== 3. Consultas =====
function listar_servicos_admin(): array
{
    return conectar()->query(
        "SELECT s.*,
                (SELECT COUNT(*) FROM servico_comodidade sc WHERE sc.servico_id = s.id) AS n_comodidades,
                (SELECT COUNT(*) FROM reservas r
                  WHERE r.servico_id = s.id AND r.estado IN ('pendente','confirmada')
                    AND r.data_fim >= CURDATE()) AS n_reservas_ativas
         FROM servicos s
         ORDER BY s.ativo DESC, s.tipo, s.nome"
    )->fetchAll();
}

function obter_servico(int $id): ?array
{
    $st = conectar()->prepare('SELECT * FROM servicos WHERE id = :id');
    $st->execute([':id' => $id]);
    return $st->fetch() ?: null;
}

function listar_comodidades(): array
{
    return conectar()->query('SELECT id, nome, icone FROM comodidades ORDER BY nome')->fetchAll();
}

function ids_comodidades_do_servico(int $id): array
{
    $st = conectar()->prepare('SELECT comodidade_id FROM servico_comodidade WHERE servico_id = :id');
    $st->execute([':id' => $id]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// ===== 4. Gravar serviço + comodidades (tudo ou nada) =====
function guardar_servico(?int $id, array $d, array $comodidades): int
{
    $pdo = conectar();
    $pdo->beginTransaction();

    try {
        if ($id === null) {
            $st = $pdo->prepare(
                'INSERT INTO servicos (nome, tipo, descricao, preco, unidade, capacidade, quantidade, ativo)
                 VALUES (:nome, :tipo, :desc, :preco, :unidade, :cap, :qtd, :ativo)'
            );
            $st->execute([
                ':nome' => $d['nome'], ':tipo' => $d['tipo'],
                ':desc' => $d['descricao'] !== '' ? $d['descricao'] : null,
                ':preco' => $d['preco'], ':unidade' => UNIDADE_POR_TIPO[$d['tipo']],
                ':cap' => $d['capacidade'], ':qtd' => $d['quantidade'], ':ativo' => $d['ativo'],
            ]);
            $id = (int)$pdo->lastInsertId();
        } else {
            // tipo e unidade NÃO são alterados
            $st = $pdo->prepare(
                'UPDATE servicos
                 SET nome = :nome, descricao = :desc, preco = :preco,
                     capacidade = :cap, quantidade = :qtd, ativo = :ativo
                 WHERE id = :id'
            );
            $st->execute([
                ':nome' => $d['nome'],
                ':desc' => $d['descricao'] !== '' ? $d['descricao'] : null,
                ':preco' => $d['preco'], ':cap' => $d['capacidade'],
                ':qtd' => $d['quantidade'], ':ativo' => $d['ativo'], ':id' => $id,
            ]);
        }

        $pdo->prepare('DELETE FROM servico_comodidade WHERE servico_id = :id')->execute([':id' => $id]);
        if ($comodidades) {
            $ins = $pdo->prepare('INSERT INTO servico_comodidade (servico_id, comodidade_id) VALUES (:s, :c)');
            foreach ($comodidades as $cid) {
                $ins->execute([':s' => $id, ':c' => $cid]);
            }
        }

        $pdo->commit();
        return $id;

    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// ===== 5. Ativar / desativar (estado explícito: clicar duas vezes não "desfaz") =====
function definir_estado_servico(int $id, bool $ativo): string
{
    $servico = obter_servico($id);
    if (!$servico) {
        throw new RuntimeException('Serviço não encontrado.');
    }
    conectar()->prepare('UPDATE servicos SET ativo = :a WHERE id = :id')
              ->execute([':a' => $ativo ? 1 : 0, ':id' => $id]);
    return $servico['nome'];
}

// ===== 6. Comodidades =====
function validar_comodidade(array $p): array
{
    $erros = [];
    $nome  = trim((string)($p['nome'] ?? ''));
    $icone = trim((string)($p['icone'] ?? ''));

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
        $erros[] = 'O nome deve ter entre 2 e 80 caracteres.';
    }
    if (str_contains($nome, '|')) {
        $erros[] = 'O nome não pode conter o carácter "|".';
    }
    if (mb_strlen($icone) > 10) {
        $erros[] = 'O ícone deve ter no máximo 10 caracteres (um emoji chega).';
    }
    return [$erros, $nome, $icone !== '' ? $icone : '✔'];
}