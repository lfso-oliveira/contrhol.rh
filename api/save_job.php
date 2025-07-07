<?php
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    'https://formatarrh.athom.host',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
    'https://formatar.com.br',
    'https://formatar.com.br/contrhol',
    'https://formatar.com.br/contrhol/dash'
];

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json');

// Se for uma requisição OPTIONS, retornar apenas os headers
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
    // Conectar ao banco com PDO
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Log para depuração
    file_put_contents('debug.log', print_r([
        'method' => $_SERVER['REQUEST_METHOD'],
        'input' => file_get_contents('php://input'),
        'headers' => getallheaders()
    ], true), FILE_APPEND);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Buscar todas as vagas
        $status_registro = isset($_GET['status_registro']) ? $_GET['status_registro'] : 'ativo';
        $where_clause = $status_registro ? "WHERE status_registro = :status_registro" : "";
        
        $stmt = $pdo->prepare("SELECT id, company, title, description, requirements, keywords, location, salary_range, deadline, status, quantidade, date_fechamento, status_registro FROM vagas $where_clause ORDER BY deadline DESC");
        
        if ($status_registro) {
            $stmt->bindParam(':status_registro', $status_registro);
        }
        
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($jobs);
    } elseif ($method === 'POST') {
        // Criar ou editar uma vaga
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['company'], $data['title'], $data['description'], $data['requirements'], $data['location'], $data['deadline'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }
        // Gerar UUID se não vier 'id'
        if (empty($data['id'])) {
            $data['id'] = generate_uuid_v4();
        }
        // Verificar se o id já existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vagas WHERE id = :id");
        $stmt->execute(['id' => $data['id']]);
        $existe = $stmt->fetchColumn() > 0;
        if ($existe) {
            // UPDATE se já existe
            $stmt = $pdo->prepare("
                UPDATE vagas 
                SET company = :company, 
                    title = :title, 
                    description = :description, 
                    requirements = :requirements, 
                    keywords = :keywords, 
                    location = :location, 
                    salary_range = :salary_range, 
                    deadline = :deadline, 
                    status = :status, 
                    quantidade = :quantidade, 
                    date_fechamento = :date_fechamento,
                    candidato = :candidato
                WHERE id = :id
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
                'status' => $data['status'] ?? 'Em Divulgação',
                'quantidade' => $data['quantidade'] ?? 1,
                'date_fechamento' => $data['date_fechamento'] ?? null,
                'candidato' => $data['candidato'] ?? null
            ]);
            echo json_encode(['success' => 'Vaga atualizada com sucesso']);
        } else {
            // INSERT se não existe
            $stmt = $pdo->prepare("
                INSERT INTO vagas (id, company, title, description, requirements, keywords, location, salary_range, deadline, status, quantidade, date_fechamento, candidato)
                VALUES (:id, :company, :title, :description, :requirements, :keywords, :location, :salary_range, :deadline, :status, :quantidade, :date_fechamento, :candidato)
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
                'status' => $data['status'] ?? 'Em Divulgação',
                'quantidade' => $data['quantidade'] ?? 1,
                'date_fechamento' => $data['date_fechamento'] ?? null,
                'candidato' => $data['candidato'] ?? null
            ]);
            echo json_encode(['success' => 'Vaga criada com sucesso']);
        }
    } elseif ($method === 'PUT') {
        // Atualizar uma vaga existente
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'], $data['company'], $data['title'], $data['description'], $data['requirements'], $data['location'], $data['deadline'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados inválidos']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE vagas 
            SET company = :company, 
                title = :title, 
                description = :description, 
                requirements = :requirements, 
                keywords = :keywords, 
                location = :location, 
                salary_range = :salary_range, 
                deadline = :deadline, 
                status = :status, 
                quantidade = :quantidade, 
                date_fechamento = :date_fechamento,
                candidato = :candidato
            WHERE id = :id
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
            'status' => $data['status'] ?? 'Em Divulgação',
            'quantidade' => $data['quantidade'] ?? 1,
            'date_fechamento' => $data['date_fechamento'] ?? null,
            'candidato' => $data['candidato'] ?? null
        ]);
        echo json_encode(['success' => 'Vaga atualizada com sucesso']);
    } elseif ($method === 'DELETE') {
        // Excluir uma vaga (marcar como inativa)
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da vaga não fornecido']);
            exit;
        }

        // Verificar se existem candidatos para esta vaga
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM candidatos_vagas WHERE vaga_id = :id");
        $stmt->execute(['id' => $data['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['total'] > 0) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Não é possível excluir esta vaga pois existem candidatos inscritos. Se desejar, você pode suspender a vaga alterando seu status para "Suspensa".',
                'hasCandidates' => true
            ]);
            exit;
        }

        // Se não houver candidatos, prosseguir com a exclusão
        $stmt = $pdo->prepare("UPDATE vagas SET status_registro = 'inativo' WHERE id = :id");
        $stmt->execute(['id' => $data['id']]);
        echo json_encode(['success' => 'Vaga excluída com sucesso']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
    }

} catch (Exception $e) {
    http_response_code(500);
    // Log detalhado do erro
    file_put_contents('debug.log', date('Y-m-d H:i:s') . ' - ERRO: ' . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
}

// Função para gerar UUID v4
function generate_uuid_v4() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>