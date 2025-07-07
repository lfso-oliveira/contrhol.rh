<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production

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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get jobId from query parameter
    $jobId = isset($_GET['jobId']) ? $_GET['jobId'] : null;

    if (!$jobId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da vaga não fornecido']);
        exit;
    }

    // Query to fetch candidates for the given jobId
    $stmt = $pdo->prepare("
        SELECT 
            cv.id_candidatura_vaga,
            cv.status_selecao,
            cv.data_entrevista,
            cv.foi_entrevista,
            c.nome, 
            c.email, 
            ct.telefone
        FROM candidatos_vagas cv
        JOIN candidatos c ON cv.candidato_email = c.email
        JOIN contatos ct on ct.candidato_id = c.id
        WHERE cv.vaga_id = :jobId
    ");
    $stmt->execute(['jobId' => $jobId]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return candidates as JSON
    echo json_encode(['candidates' => $candidates]);
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage(), 3, '/var/log/php_errors.log');
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Unexpected Error: ' . $e->getMessage(), 3, '/var/log/php_errors.log');
    http_response_code(500);
    echo json_encode(['error' => 'Erro inesperado: ' . $e->getMessage()]);
}
?>