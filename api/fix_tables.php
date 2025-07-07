<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('INCLUDED_FILE', true);
require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = Database::connect();
    $results = [];
    
    // 1. Criar tabela empresas
    $sql = "CREATE TABLE IF NOT EXISTS empresas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(255) NOT NULL,
        logo VARCHAR(255)
    )";
    $pdo->exec($sql);
    $results['create_empresas'] = 'OK';
    
    // 2. Verificar e adicionar colunas faltantes na tabela vagas
    $columns = [
        'empresa_id' => 'INT NULL',
        'data_inscricao' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
    ];
    
    foreach ($columns as $column => $definition) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM vagas LIKE '$column'");
            if ($check->rowCount() === 0) {
                $sql = "ALTER TABLE vagas ADD COLUMN $column $definition";
                $pdo->exec($sql);
            }
        } catch (Exception $e) {
            $results["alter_vagas_$column"] = $e->getMessage();
            continue;
        }
        $results["alter_vagas_$column"] = 'OK';
    }
    
    // Adicionar foreign key se não existir
    try {
        $sql = "ALTER TABLE vagas ADD FOREIGN KEY (empresa_id) REFERENCES empresas(id)";
        $pdo->exec($sql);
        $results['add_foreign_key'] = 'OK';
    } catch (Exception $e) {
        $results['add_foreign_key'] = $e->getMessage();
    }
    
    // 3. Verificar e adicionar coluna id em candidatos_vagas
    try {
        $check = $pdo->query("SHOW COLUMNS FROM candidatos_vagas LIKE 'id'");
        if ($check->rowCount() === 0) {
            $sql = "ALTER TABLE candidatos_vagas ADD COLUMN id INT PRIMARY KEY AUTO_INCREMENT FIRST";
            $pdo->exec($sql);
        }
        $results['alter_candidatos_vagas'] = 'OK';
    } catch (Exception $e) {
        $results['alter_candidatos_vagas'] = $e->getMessage();
    }
    
    // 4. Atualizar a query em get_jobs.php
    $getJobsPath = __DIR__ . '/get_jobs.php';
    $getJobsContent = file_get_contents($getJobsPath);
    
    // Nova query que funciona sem a tabela empresas
    $newQuery = "SELECT 
        v.*,
        v.company as empresa_nome,
        NULL as empresa_logo,
        COUNT(DISTINCT cv.candidato_email) as total_candidatos
    FROM vagas v
    LEFT JOIN candidatos_vagas cv ON v.id = cv.vaga_id
    GROUP BY v.id
    ORDER BY v.created_at DESC";
    
    // Substituir a query antiga pela nova
    $pattern = '/\$query\s*=\s*"[^"]+"/';
    $replacement = '$query = "' . $newQuery . '"';
    $getJobsContent = preg_replace($pattern, $replacement, $getJobsContent);
    
    if (file_put_contents($getJobsPath, $getJobsContent) !== false) {
        $results['update_get_jobs'] = 'OK';
    } else {
        $results['update_get_jobs'] = 'Falha ao atualizar o arquivo';
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 