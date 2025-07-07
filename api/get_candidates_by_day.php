<?php
// Prevenir output de erros PHP que possam corromper o JSON
ob_start();

// Headers necessários
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Habilitar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Definir constante para evitar warning no require
define('INCLUDED_FILE', true);

// Log de início da execução
error_log("[get_candidates_by_day.php] Iniciando execução");

try {
    // Verificar arquivo de configuração
    $configPath = __DIR__ . '/../../config/database.php';
    if (!file_exists($configPath)) {
        throw new Exception("Arquivo de configuração não encontrado: " . $configPath);
    }
    
    require_once $configPath;
    
    // Pegar parâmetros de data
    $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
    $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
    
    error_log("[get_candidates_by_day.php] Parâmetros: data_inicio=" . ($dataInicio ?? 'null') . ", data_fim=" . ($dataFim ?? 'null'));

    // Validar datas
    if ($dataInicio && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
        throw new Exception('Data inicial inválida');
    }
    if ($dataFim && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
        throw new Exception('Data final inválida');
    }

    // Conectar ao banco
    $pdo = Database::connect();
    error_log("[get_candidates_by_day.php] Conexão estabelecida");

    // Verificar se as tabelas existem
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'candidatos_vagas'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception('A tabela candidatos_vagas não existe no banco de dados');
    }
    
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'candidatos'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception('A tabela candidatos não existe no banco de dados');
    }

    // Construir a consulta SQL base
    $sql = "SELECT 
        DATE(cv.`atualizado_em`) AS data,
        COUNT(DISTINCT cv.candidato_email) AS quantidade
    FROM candidatos_vagas cv
    INNER JOIN candidatos c ON cv.candidato_email = c.email";

    // Adicionar filtros de data se fornecidos
    if ($dataInicio && $dataFim) {
        $sql .= " WHERE DATE(cv.`atualizado_em`) BETWEEN :data_inicio AND :data_fim";
        $params = [
            ':data_inicio' => $dataInicio,
            ':data_fim' => $dataFim
        ];
    } else {
        $sql .= " WHERE cv.`atualizado_em` >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)";
        $params = [];
    }

    // Adicionar GROUP BY e ORDER BY
    $sql .= " GROUP BY DATE(cv.`atualizado_em`)
              ORDER BY data";

    error_log("[get_candidates_by_day.php] SQL: " . $sql);
    error_log("[get_candidates_by_day.php] Parâmetros: " . json_encode($params));

    // Preparar e executar a consulta
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    // Buscar resultados
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[get_candidates_by_day.php] Registros encontrados: " . count($result));

    // Se não houver resultados, retornar array vazio
    if (empty($result)) {
        error_log("[get_candidates_by_day.php] Nenhum registro encontrado");
        ob_end_clean();
        echo json_encode([]);
        exit;
    }

    // Formatar as datas
    $formattedResult = array_map(function($row) {
        $date = new DateTime($row['data']);
        return [
            'data' => $date->format('d/m/Y'),
            'quantidade' => (int)$row['quantidade']
        ];
    }, $result);

    error_log("[get_candidates_by_day.php] Resposta: " . json_encode($formattedResult));
    
    // Limpar qualquer output anterior e enviar apenas o JSON
    ob_end_clean();
    echo json_encode($formattedResult);

} catch (PDOException $e) {
    error_log("[get_candidates_by_day.php] Erro PDO: " . $e->getMessage());
    error_log("[get_candidates_by_day.php] Stack trace: " . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao acessar o banco de dados',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("[get_candidates_by_day.php] Erro: " . $e->getMessage());
    error_log("[get_candidates_by_day.php] Stack trace: " . $e->getTraceAsString());
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