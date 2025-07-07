<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../../config/database.php';
    $pdo = Database::connect();
    $stmt = $pdo->query('SELECT id, nome, logo FROM empresas ORDER BY nome');
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($empresas, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Erro ao buscar empresas', 'details' => $e->getMessage()]);
} 