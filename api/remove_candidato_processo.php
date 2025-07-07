<?php
// dash/api/remove_candidato_processo.php

header('Content-Type: application/json');
// Configurações de CORS
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
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS, DELETE'); // Permitir DELETE
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Idealmente usar POST ou DELETE, mas para simplificar com JSON no corpo, POST é comum.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Poderia ser 'DELETE' também
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input) || !isset($input['id_candidatura_vaga']) || !is_numeric($input['id_candidatura_vaga'])) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'ID da candidatura inválido ou não fornecido.']);
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

    $sql = "DELETE FROM candidatos_vagas WHERE id_candidatura_vaga = :id_candidatura_vaga";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_candidatura_vaga', $id_candidatura_vaga, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Candidato removido do processo seletivo com sucesso.']);
        } else {
            // Nenhum registro foi deletado, o ID pode não existir
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Candidato não encontrado neste processo seletivo ou já removido.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao tentar remover o candidato.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    // error_log("Erro em remove_candidato_processo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados ao remover candidato.', 'debug_error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    // error_log("Erro geral em remove_candidato_processo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado.', 'debug_error' => $e->getMessage()]);
}

?> 