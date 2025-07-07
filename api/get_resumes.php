<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("Requisição recebida em get_resumes.php");
error_log("GET params: " . print_r($_GET, true));

define('INCLUDED_FILE', true);

try {
    $configPath = __DIR__ . '/../../config/database.php';
    error_log("Tentando incluir arquivo de configuração: " . $configPath);
    
    if (!file_exists($configPath)) {
        throw new Exception("Arquivo de configuração não encontrado: " . $configPath);
    }
    
    require_once $configPath;
    error_log("Arquivo de configuração incluído com sucesso");

    error_log("Tentando conectar ao banco de dados");
    try {
        $pdo = Database::connect();
        error_log("Conexão estabelecida com sucesso");
        
        $testStmt = $pdo->query("SELECT 1");
        if ($testStmt) {
            error_log("Teste de conexão bem sucedido");
        }
    } catch (PDOException $e) {
        error_log("Erro ao conectar ao banco de dados: " . $e->getMessage());
        throw new Exception("Erro ao conectar ao banco de dados: " . $e->getMessage());
    }

    $query = "
        SELECT DISTINCT
            c.*,
            cu.arquivo_caminho as curriculo,
            DATE_FORMAT(c.created_at, '%Y-%m-%d') as data,
            p.area_interesse,
            p.linkedin,
            p.pretensao_salarial,
            p.disponibilidade,
            ct.telefone,
            ct.nascimento,
            ct.cep,
            ct.estado,
            ct.cidade
        FROM candidatos c
        LEFT JOIN curriculos cu ON c.id = cu.candidato_id
        LEFT JOIN preferencias p ON c.id = p.candidato_id
        LEFT JOIN contatos ct ON c.id = ct.candidato_id
        WHERE 1=1
    ";
    $params = [];

    error_log("Data início recebida: " . ($_GET['data_inicio'] ?? 'não definida'));
    error_log("Data fim recebida: " . ($_GET['data_fim'] ?? 'não definida'));

    if (!empty($_GET['data_inicio'])) {
        $query .= " AND DATE(c.created_at) >= :data_inicio";
        $params[':data_inicio'] = $_GET['data_inicio'];
    }

    if (!empty($_GET['data_fim'])) {
        $query .= " AND DATE(c.created_at) <= :data_fim";
        $params[':data_fim'] = $_GET['data_fim'];
    }

    if (!empty($_GET['area'])) {
        $query .= " AND c.area = :area";
        $params[':area'] = $_GET['area'];
    }

    if (!empty($_GET['escolaridade'])) {
        $query .= " AND c.escolaridade = :escolaridade";
        $params[':escolaridade'] = $_GET['escolaridade'];
    }

    if (!empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $query .= " AND (
            c.nome LIKE :search1 OR
            c.email LIKE :search2 OR
            p.area_interesse LIKE :search3 OR
            ct.cidade LIKE :search4 OR
            ct.estado LIKE :search5
        )";
        $params[':search1'] = $searchTerm;
        $params[':search2'] = $searchTerm;
        $params[':search3'] = $searchTerm;
        $params[':search4'] = $searchTerm;
        $params[':search5'] = $searchTerm;
    }

    error_log("Query final: " . $query);
    error_log("Parâmetros finais: " . print_r($params, true));

    $query .= " ORDER BY c.created_at DESC";

    try {
        $stmt = $pdo->prepare($query);
        if (!$stmt) {
            error_log("Erro ao preparar a query: " . print_r($pdo->errorInfo(), true));
            throw new Exception("Erro ao preparar a query");
        }
        
        if (!$stmt->execute($params)) {
            error_log("Erro ao executar a query: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Erro ao executar a query");
        }
        
        $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Query executada com sucesso. Total de resultados: " . count($candidatos));
    } catch (PDOException $e) {
        error_log("PDO Exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }

    error_log("Total de candidatos encontrados: " . count($candidatos));
    if (count($candidatos) > 0) {
        error_log("Primeira data encontrada: " . $candidatos[0]['created_at']);
    }

    foreach ($candidatos as &$candidato) {
        try {
            $expQuery = "SELECT * FROM experiencias WHERE candidato_id = ?";
            $stmtExp = $pdo->prepare($expQuery);
            $stmtExp->execute([$candidato['id']]);
            $candidato['experiences'] = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

            $qualQuery = "SELECT * FROM qualificacoes WHERE candidato_id = ?";
            $stmtQual = $pdo->prepare($qualQuery);
            $stmtQual->execute([$candidato['id']]);
            $candidato['qualifications'] = $stmtQual->fetchAll(PDO::FETCH_ASSOC);

            if ($candidato['curriculo']) {
                $candidato['curriculo'] = '/public/curriculos/' . rawurlencode(basename($candidato['curriculo']));
                
                if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                    $candidato['curriculo'] = '/public_html' . $candidato['curriculo'];
                }
            } else {
                $candidato['curriculo'] = '#';
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar dados adicionais do candidato {$candidato['id']}: " . $e->getMessage());

            $candidato['experiences'] = [];
            $candidato['qualifications'] = [];
            $candidato['curriculo'] = '#';
        }
    }

    Database::disconnect();

    echo json_encode($candidatos);

} catch (PDOException $e) {
    error_log("Erro PDO em get_resumes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => true, 
        'message' => 'Erro de banco de dados: ' . $e->getMessage(),
        'details' => [
            'sql_state' => $e->errorInfo[0] ?? null,
            'sql_code' => $e->errorInfo[1] ?? null,
            'sql_message' => $e->errorInfo[2] ?? null
        ]
    ]);
} catch (Exception $e) {
    error_log("Erro em get_resumes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => true, 
        'message' => $e->getMessage(),
        'details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>