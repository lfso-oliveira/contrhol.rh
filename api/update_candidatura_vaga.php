<?php
// dash/api/update_candidatura_vaga.php

header('Content-Type: application/json');
// Configurações de CORS (semelhantes ao script get_processo_seletivo_data.php)
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    'https://formatarrh.athom.host',
    'http://localhost',
    'http://127.0.0.1',
    'https://formatar.com.br',
    'https://formatar.com.br/contrhol',
    'https://formatar.com.br/contrhol/dash'
];

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *'); // Considere restringir em produção
}
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Este endpoint usará POST
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST.']);
    exit();
}

// Obter dados do corpo da requisição (JSON)
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input) || !isset($input['id_candidatura_vaga'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dados inválidos. id_candidatura_vaga é obrigatório.']);
    exit();
}

$id_candidatura_vaga = (int)$input['id_candidatura_vaga'];

// Configuração do banco de dados
$dbConfig = [
    'host' => '143.137.189.66',
    'port' => '7009',
    'user' => 'root',
    'password' => 'Formatar123',
    'database' => 'formatarrh'
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Montar a query de UPDATE dinamicamente
    $setClauses = [];
    $params = [':id_candidatura_vaga' => $id_candidatura_vaga];

    // Status da Seleção
    if (isset($input['status_selecao'])) {
        // Validar os status permitidos
        $status_permitidos = ['Inscrito', 'Triagem', 'Entrevista Agendada', 'Entrevistado', 'Aprovado', 'Rejeitado', 'Contratado'];
        if (in_array($input['status_selecao'], $status_permitidos)) {
            $setClauses[] = "status_selecao = :status_selecao";
            $params[':status_selecao'] = $input['status_selecao'];
        } else {
            // Opcional: retornar um erro se o status for inválido
            // http_response_code(400);
            // echo json_encode(['success' => false, 'message' => 'Status de seleção inválido.']);
            // exit();
        }
    }

    // Data da Entrevista
    // Espera-se que a data venha no formato YYYY-MM-DD HH:MM:SS ou null
    if (array_key_exists('data_entrevista', $input)) { // Usar array_key_exists para permitir null
        if ($input['data_entrevista'] === null || $input['data_entrevista'] === '') {
            $setClauses[] = "data_entrevista = NULL";
        } else {
            // Tentar converter para o formato do MySQL se necessário, ou validar
            // Exemplo de validação simples (pode ser mais robusta)
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $input['data_entrevista']);
            if ($dateTime && $dateTime->format('Y-m-d H:i:s') == $input['data_entrevista']) {
                $setClauses[] = "data_entrevista = :data_entrevista";
                $params[':data_entrevista'] = $input['data_entrevista'];
            } else {
                 // Tentar formatar se vier como d/m/Y H:i (comum em interfaces)
                $dateTimeAlt = DateTime::createFromFormat('d/m/Y H:i', $input['data_entrevista']);
                if ($dateTimeAlt) {
                    $setClauses[] = "data_entrevista = :data_entrevista";
                    $params[':data_entrevista'] = $dateTimeAlt->format('Y-m-d H:i:s');
                } else {
                    // Opcional: retornar erro se o formato da data for inválido
                }
            }
        }
    }

    // Observações da Entrevista
    if (array_key_exists('observacoes_entrevista', $input)) { // Permite string vazia ou null
        $setClauses[] = "observacoes_entrevista = :observacoes_entrevista";
        $params[':observacoes_entrevista'] = $input['observacoes_entrevista'];
    }

    // Foi à Entrevista
    if (array_key_exists('foi_entrevista', $input)) { // Permite string vazia ou null
        $setClauses[] = "foi_entrevista = :foi_entrevista";
        $params[':foi_entrevista'] = (string)$input['foi_entrevista']; // Garante varchar
    }

    if (empty($setClauses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar foi fornecido.']);
        exit();
    }

    $sql = "UPDATE candidatos_vagas SET " . implode(", ", $setClauses) . " WHERE id_candidatura_vaga = :id_candidatura_vaga";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Candidatura atualizada com sucesso.']);
    } else {
        // Isso pode acontecer se os dados enviados forem os mesmos já existentes no banco
        // ou se o id_candidatura_vaga não existir (mas a query não daria erro nesse caso, apenas 0 linhas afetadas).
        echo json_encode(['success' => true, 'message' => 'Nenhuma alteração realizada (os dados podem já estar atualizados ou o ID não foi encontrado).']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    // error_log("Erro em update_candidatura_vaga.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar candidatura.', 'debug_error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    // error_log("Erro geral em update_candidatura_vaga.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado ao atualizar.', 'debug_error' => $e->getMessage()]);
}

?> 