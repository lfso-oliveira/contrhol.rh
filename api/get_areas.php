<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("[get_areas.php] Iniciando execução");

define('INCLUDED_FILE', true);

try {
    $configPath = __DIR__ . '/../../config/database.php';
    error_log("[get_areas.php] Tentando incluir arquivo de configuração: " . $configPath);
    
    if (!file_exists($configPath)) {
        throw new Exception("Arquivo de configuração não encontrado: " . $configPath);
    }
    
    require_once $configPath;
    error_log("[get_areas.php] Arquivo de configuração incluído com sucesso");

    error_log("[get_areas.php] Tentando conectar ao banco de dados");
    $pdo = Database::connect();
    error_log("[get_areas.php] Conexão estabelecida com sucesso");

    error_log("[get_areas.php] Verificando se a tabela vagas existe");
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'vagas'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception('A tabela vagas não existe no banco de dados');
    }
    error_log("[get_areas.php] Tabela vagas encontrada");

    $query = "SELECT DISTINCT area_atuacao FROM vagas WHERE area_atuacao IS NOT NULL AND area_atuacao != '' ORDER BY area_atuacao";
    error_log("[get_areas.php] Executando query: " . $query);
    
    $stmt = $pdo->query($query);
    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    error_log("[get_areas.php] Total de áreas encontradas: " . count($areas));
    error_log("[get_areas.php] Áreas: " . print_r($areas, true));

    ob_end_clean();
    echo json_encode($areas);

} catch (PDOException $e) {
    error_log("[get_areas.php] Erro PDO: " . $e->getMessage());
    error_log("[get_areas.php] Stack trace: " . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("[get_areas.php] Erro: " . $e->getMessage());
    error_log("[get_areas.php] Stack trace: " . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if (isset($pdo)) {
        Database::disconnect();
    }
} 