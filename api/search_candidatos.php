<?php
header('Content-Type: application/json; charset=utf-8');

// CORS básico
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    'https://formatarrh.athom.host',
    'http://localhost',
    'http://127.0.0.1',
    'https://formatar.com.br',
    'https://formatar.com.br/contrhol',
    'https://formatar.com.br/contrhol/dash'
];
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Receber termo de busca
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = Database::connect();
    $sql = "SELECT id, nome, email FROM candidatos WHERE nome LIKE :q ORDER BY nome ASC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $like = '%' . $q . '%';
    $stmt->bindParam(':q', $like, PDO::PARAM_STR);
    $stmt->execute();
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $candidatos]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar candidatos.', 'debug_error' => $e->getMessage()]);
} 