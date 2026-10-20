<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/reservas_funcoes.php';

$config   = obter_configuracoes();
$servicos = listar_servicos();
$erros    = [];

$old = [
    'servico_id'  => 0,
    'nome'        => '',
    'email'       => '',
    'telefone'    => '',
    'data_inicio' => '',
    'data_fim'    => '',
    'num_pessoas' => 1,
    'observacoes' => '',
];

// ===== Processar o formulário =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old = [
        'servico_id'  => (int)($_POST['servico_id'] ?? 0),
        'nome'        => trim($_POST['nome'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'telefone'    => trim($_POST['telefone'] ?? ''),
        'data_inicio' => trim($_POST['data_inicio'] ?? ''),
        'data_fim'    => trim($_POST['data_fim'] ?? ''),
        'num_pessoas' => (int)($_POST['num_pessoas'] ?? 0),
        'observacoes' => trim($_POST['observacoes'] ?? ''),
    ];

    if (!validar_csrf($_POST['csrf'] ?? null)) {
        $erros[] = 'Sessão inválida. Recarregue a página e tente novamente.';
    } else {
        $servico = $servicos[$old['servico_id']] ?? null;
        if (!$servico) {
            $erros[] = 'Escolha um serviço válido.';
        }

        $tamNome = mb_strlen($old['nome']);
        if ($tamNome < 3 || $tamNome > 100) {
            $erros[] = 'O nome deve ter entre 3 e 100 caracteres.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'O email introduzido não é válido.';
        }
        if (!preg_match('/^\+?[0-9\s\-]{9,20}$/', $old['telefone'])) {
            $erros[] = 'O telefone não é válido (ex.: +258 84 000 0000).';
        }
        if (mb_strlen($old['observacoes']) > 500) {
            $erros[] = 'As observações ultrapassam 500 caracteres.';
        }

        // Eventos têm uma só data
        if ($servico && $servico['unidade'] === 'evento') {
            $old['data_fim'] = $old['data_inicio'];
        }

        $ini = parse_data($old['data_inicio']);
        $fim = parse_data($old['data_fim']);

        if (!$ini || !$fim) {
            $erros[] = 'Indique datas válidas.';
        } else {
            $hoje = new DateTimeImmutable('today');
            if ($ini < $hoje) {
                $erros[] = 'A data de início não pode estar no passado.';
            } elseif ($fim < $ini) {
                $erros[] = 'A data final não pode ser anterior à inicial.';
            } elseif ($servico && $servico['unidade'] === 'noite' && $fim == $ini) {
                $erros[] = 'A estadia mínima é de 1 noite.';
            }
        }

        if ($servico && ($old['num_pessoas'] < 1 || $old['num_pessoas'] > (int)$servico['capacidade'])) {
            $erros[] = 'O número de pessoas deve ser entre 1 e ' . (int)$servico['capacidade'] . '.';
        }

        if (empty($erros)) {
            try {
                $codigo = criar_reserva($old['servico_id'], $old, $ini, $fim);
                $_SESSION['reserva_ok'] = $codigo;
                header('Location: reservas.php');
                exit;
            } catch (RuntimeException $ex) {
                $erros[] = $ex->getMessage();
            }

            if (empty($_POST['aceito_termos'])) {
                $erros[] = 'Tem de aceitar os Termos e Condições para reservar.';
            }
        }
    }
}

$sucesso = $_SESSION['reserva_ok'] ?? null;
unset($_SESSION['reserva_ok']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservas | <?= e($config['nome_estabelecimento'] ?? 'Hotel') ?></title>
    <?php require __DIR__ . '/includes/pwa_scripts.php'; ?>
    <?php require __DIR__ . '/includes/pwa_head.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">
<header class="bg-blue-700 text-white py-6 text-center">
    <h1 class="text-2xl font-bold"><?= e($config['nome_estabelecimento'] ?? '') ?></h1>
    <p class="text-blue-100 text-sm mt-1">Faça a sua reserva</p>
</header>

<main class="max-w-3xl mx-auto p-4 sm:p-8">

    <?php if ($sucesso): ?>
        <div class="mb-6 p-4 rounded bg-green-100 text-green-800">
            <p class="font-semibold">Reserva registada com sucesso!</p>
            <p>O seu código é <span class="font-mono font-bold text-lg"><?= e($sucesso) ?></span>.
               Guarde-o. A sua reserva está <strong>pendente</strong> até confirmação da nossa equipa.</p>
        </div>
    <?php endif; ?>

    <?php if ($erros): ?>
        <div class="mb-6 p-3 rounded bg-red-100 text-red-800">
            <ul class="list-disc ml-5">
                <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-8">
        <input type="hidden" name="csrf" value="<?= e(token_csrf()) ?>">

        <!-- 1. Escolha do serviço -->
        <section>
            <h2 class="text-lg font-semibold text-gray-800 mb-3">1. Escolha o serviço</h2>
            <div class="space-y-3">
                <?php foreach ($servicos as $s): ?>
                    <label class="block cursor-pointer">
                        <input type="radio" name="servico_id" value="<?= (int)$s['id'] ?>"
                               data-unidade="<?= e($s['unidade']) ?>"
                               class="peer sr-only"
                               <?= $old['servico_id'] === (int)$s['id'] ? 'checked' : '' ?>>
                        <div class="border-2 border-gray-200 rounded-lg p-4 bg-white
                                    peer-checked:border-blue-600 peer-checked:bg-blue-50
                                    peer-focus-visible:ring-2 peer-focus-visible:ring-blue-400">
                            <div class="flex justify-between items-start gap-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?= e($s['nome']) ?></h3>
                                    <p class="text-sm text-gray-600"><?= e($s['descricao']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1">Até <?= (int)$s['capacidade'] ?> pessoa(s)</p>
                                </div>
                                <p class="text-right font-bold text-blue-700 whitespace-nowrap">
                                    <?= e(formatar_mt($s['preco'])) ?>
                                    <span class="block text-xs font-normal text-gray-500">
                                        <?= $s['unidade'] === 'evento' ? 'por evento' : 'por ' . e($s['unidade']) ?>
                                    </span>
                                </p>
                            </div>

                            <?php if ($s['lista_comodidades']): ?>
                                <ul class="flex flex-wrap gap-2 mt-3">
                                    <?php foreach ($s['lista_comodidades'] as $com): ?>
                                        <li class="text-xs bg-gray-100 text-gray-700 rounded-full px-2 py-1">
                                            <?= e($com) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 2. Datas -->
        <section class="bg-white p-5 rounded-lg shadow">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">2. Datas</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label id="rotulo-inicio" for="data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data de início</label>
                    <input type="date" id="data_inicio" name="data_inicio" min="<?= date('Y-m-d') ?>"
                           value="<?= e($old['data_inicio']) ?>"
                           class="w-full border border-gray-300 rounded p-2">
                </div>
                <div id="bloco-fim">
                    <label id="rotulo-fim" for="data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data final</label>
                    <input type="date" id="data_fim" name="data_fim" min="<?= date('Y-m-d') ?>"
                           value="<?= e($old['data_fim']) ?>"
                           class="w-full border border-gray-300 rounded p-2">
                </div>
            </div>
        </section>

        <!-- 3. Dados do cliente -->
        <section class="bg-white p-5 rounded-lg shadow space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">3. Os seus dados</h2>

            <div>
                <label for="nome" class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                <input type="text" id="nome" name="nome" maxlength="100" value="<?= e($old['nome']) ?>"
                       class="w-full border border-gray-300 rounded p-2">
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" maxlength="100" value="<?= e($old['email']) ?>"
                           class="w-full border border-gray-300 rounded p-2">
                </div>
                <div>
                    <label for="telefone" class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                    <input type="tel" id="telefone" name="telefone" maxlength="30" value="<?= e($old['telefone']) ?>"
                           class="w-full border border-gray-300 rounded p-2">
                </div>
            </div>
            <div>
                <label for="num_pessoas" class="block text-sm font-medium text-gray-700 mb-1">Número de pessoas</label>
                <input type="number" id="num_pessoas" name="num_pessoas" min="1"
                       value="<?= (int)$old['num_pessoas'] ?>"
                       class="w-full sm:w-40 border border-gray-300 rounded p-2">
            </div>
            <div>
                <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações (opcional)</label>
                <textarea id="observacoes" name="observacoes" rows="3" maxlength="500"
                          class="w-full border border-gray-300 rounded p-2"><?= e($old['observacoes']) ?></textarea>
            </div>
        </section>
        
        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox" name="aceito_termos" value="1" class="mt-1">
            <span>Li e aceito os
                <a href="termos.php" target="_blank" class="text-blue-700 underline">Termos, Condições e Política de Cancelamento</a>.
            </span>
        </label>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">
            Reservar
        </button>
    </form>
</main>

<script>
// Adapta o formulário ao tipo de serviço escolhido
const radios     = document.querySelectorAll('input[name="servico_id"]');
const blocoFim   = document.getElementById('bloco-fim');
const inputFim   = document.getElementById('data_fim');
const rotuloIni  = document.getElementById('rotulo-inicio');
const rotuloFim  = document.getElementById('rotulo-fim');

const textos = {
    noite:  { ini: 'Check-in',     fim: 'Check-out' },
    dia:    { ini: 'Levantamento', fim: 'Devolução' },
    evento: { ini: 'Data do evento', fim: '' }
};

function atualizar() {
    const sel = document.querySelector('input[name="servico_id"]:checked');
    if (!sel) return;

    const t = textos[sel.dataset.unidade];
    rotuloIni.textContent = t.ini;
    rotuloFim.textContent = t.fim;

    const evento = sel.dataset.unidade === 'evento';
    blocoFim.classList.toggle('hidden', evento);
    inputFim.disabled = evento;
}

radios.forEach(r => r.addEventListener('change', atualizar));
atualizar();
</>
</body>
</html>