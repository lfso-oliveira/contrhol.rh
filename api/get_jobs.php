<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


error_log("[get_jobs.php] Iniciando execução");

define('INCLUDED_FILE', true);

try {
    $configPath = __DIR__ . '/../../config/database.php';
    error_log("[get_jobs.php] Tentando incluir arquivo de configuração: " . $configPath);
    
    if (!file_exists($configPath)) {
        throw new Exception("Arquivo de configuração não encontrado: " . $configPath);
    }
    
    require_once $configPath;
    error_log("[get_jobs.php] Arquivo de configuração incluído com sucesso");

    error_log("[get_jobs.php] Tentando conectar ao banco de dados");
    $pdo = Database::connect();
    error_log("[get_jobs.php] Conexão estabelecida com sucesso");

    // Verificar se as tabelas existem
    $tables = ['vagas', 'empresas', 'candidatos_vagas'];
    foreach ($tables as $table) {
        $tableCheck = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->rowCount() === 0) {
            throw new Exception("A tabela $table não existe no banco de dados");
        }
    }
    error_log("[get_jobs.php] Todas as tabelas necessárias encontradas");
    
    $query = "SELECT 
        v.*,
        v.company as empresa_nome,
        NULL as empresa_logo,
        COUNT(DISTINCT cv.candidato_email) as total_candidatos
    FROM vagas v
    LEFT JOIN candidatos_vagas cv ON v.id = cv.vaga_id
    GROUP BY v.id
    ORDER BY v.created_at DESC";
    
    error_log("[get_jobs.php] Executando query: " . $query);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $vagas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("[get_jobs.php] Total de vagas encontradas: " . count($vagas));

    ob_end_clean();
    echo json_encode($vagas);
    
} catch (PDOException $e) {
    error_log("[get_jobs.php] Erro PDO: " . $e->getMessage());
    error_log("[get_jobs.php] Stack trace: " . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'error' => true, 
        'message' => 'Erro ao buscar vagas: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("[get_jobs.php] Erro: " . $e->getMessage());
    error_log("[get_jobs.php] Stack trace: " . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'error' => true, 
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($pdo)) {
        Database::disconnect();
    }
}
?> 