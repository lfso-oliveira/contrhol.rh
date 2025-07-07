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
    
    $requiredTables = [
        'vagas' => [
            'id',
            'empresa_id',
            'title',
            'description',
            'requirements',
            'location',
            'deadline',
            'data_inscricao'
        ],
        'empresas' => [
            'id',
            'nome',
            'logo'
        ],
        'candidatos' => [
            'id',
            'nome',
            'email',
            'created_at'
        ],
        'curriculos' => [
            'id',
            'candidato_id',
            'arquivo_caminho'
        ],
        'contatos' => [
            'id',
            'candidato_id',
            'telefone',
            'nascimento',
            'cep',
            'estado',
            'cidade'
        ],
        'preferencias' => [
            'id',
            'candidato_id',
            'area_interesse',
            'linkedin',
            'pretensao_salarial',
            'disponibilidade'
        ],
        'candidatos_vagas' => [
            'id',
            'vaga_id',
            'candidato_email'
        ]
    ];
    
    $results = [];
    
    foreach ($requiredTables as $table => $columns) {
        // Verificar se a tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE '$table'");
        $tableExists = $tableCheck->rowCount() > 0;
        
        $columnStatus = [];
        if ($tableExists) {
            // Verificar colunas
            $stmt = $pdo->query("SHOW COLUMNS FROM $table");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($columns as $column) {
                $columnStatus[$column] = in_array($column, $existingColumns);
            }
        }
        
        $results[$table] = [
            'exists' => $tableExists,
            'columns' => $columnStatus
        ];
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