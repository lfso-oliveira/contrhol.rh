<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://formatarrh.athom.host'); // Substitua pelo seu domínio
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração do banco de dados
$dbConfig = [
    'host' => '143.137.189.66',
    'port' => '7009',
    'user' => 'root',
    'password' => 'Formatar123',
    'database' => 'formatarrh'
];

try {
    // Conectar ao banco com PDO
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Fetch all job postings
        $stmt = $pdo->query("
    SELECT v.id, v.title, v.company, v.status, v.deadline, 
           DATEDIFF(v.deadline, CURDATE()) AS dias_restantes,
           COUNT(cv.vaga_id) AS total_inscritos
    FROM vagas v
    LEFT JOIN candidatos_vagas cv ON v.id = cv.vaga_id
    GROUP BY v.id
    ORDER BY v.deadline ASC
");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($jobs);
    } elseif ($method === 'POST') {
        // Create a new job posting
        $data = json_decode(file_get_contents('php://input'), true);
       if (!$data || !isset($data['id'], $data['company'], $data['title'], $data['description'], $data['requirements'], $data['location'], $data['deadline'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

        $stmt = $pdo->prepare("
    INSERT INTO vagas (id, company, title, description, requirements, keywords, location, salary_range, deadline, status, quantidade, date_fechamento, escolaridade)
    VALUES (:id, :company, :title, :description, :requirements, :keywords, :location, :salary_range, :deadline, :status, :quantidade, :date_fechamento, :escolaridade)
");
$stmt->execute([
    'id' => $data['id'],
    'company' => $data['company'],
    'title' => $data['title'],
    'description' => $data['description'],
    'requirements' => $data['requirements'],
    'keywords' => $data['keywords'] ?? null,
    'location' => $data['location'],
    'salary_range' => $data['salary_range'] ?? null,
    'deadline' => $data['deadline'],
    'status' => $data['status'] ?? 'aberta', // valor padrão
    'quantidade' => $data['quantidade'] ?? 1, // valor padrão
    'date_fechamento' => $data['date_fechamento'] ?? null, // opcional
    'escolaridade' => $data['escolaridade'] ?? null,
]);
        echo json_encode(['success' => 'Vaga criada com sucesso']);
    } elseif ($method === 'DELETE') {
        // Delete a job posting
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da vaga não fornecido']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM vagas WHERE id = :id");
        $stmt->execute(['id' => $data['id']]);
        echo json_encode(['success' => 'Vaga excluída com sucesso']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
}
?>