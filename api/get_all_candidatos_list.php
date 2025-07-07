<?php
// dash/api/get_all_candidatos_list.php

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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

    // Selecionar id, nome e email dos candidatos. 
    // O id aqui é o da tabela `candidatos` (c.id em queries anteriores), importante para referências futuras se necessário.
    // No entanto, para adicionar à vaga, usaremos o email conforme a estrutura de `candidatos_vagas`.
    $stmt = $pdo->query("SELECT id, nome, email FROM candidatos ORDER BY nome ASC");
    $candidatos = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $candidatos]);

} catch (PDOException $e) {
    http_response_code(500);
    // error_log("Erro em get_all_candidatos_list.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados ao buscar lista de candidatos.', 'debug_error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    // error_log("Erro geral em get_all_candidatos_list.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado.', 'debug_error' => $e->getMessage()]);
}

?> 