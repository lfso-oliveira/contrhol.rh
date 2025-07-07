<?php
header('Content-Type: application/json; charset=utf-8');

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Método não permitido']);
    exit;
}

// Receber dados do POST
$data = json_decode(file_get_contents('php://input'), true);
$candidato_email = isset($data['candidato_email']) ? trim($data['candidato_email']) : null;
$vaga_id = isset($data['vaga_id']) ? trim($data['vaga_id']) : null;

if (!$candidato_email || !$vaga_id) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Dados obrigatórios não enviados.']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = Database::connect();
    // Verificar se já existe inscrição desse candidato para essa vaga
    $check = $pdo->prepare('SELECT COUNT(*) FROM candidatos_vagas WHERE candidato_email = :candidato_email AND vaga_id = :vaga_id');
    $check->execute([':candidato_email' => $candidato_email, ':vaga_id' => $vaga_id]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['error' => true, 'message' => 'Candidato já inscrito nesta vaga.']);
        exit;
    }
    // Inserir novo registro
    $stmt = $pdo->prepare('INSERT INTO candidatos_vagas (candidato_email, vaga_id, data_inscricao) VALUES (:candidato_email, :vaga_id, NOW())');
    $stmt->execute([
        ':candidato_email' => $candidato_email,
        ':vaga_id' => $vaga_id
    ]);
    echo json_encode(['success' => true, 'message' => 'Candidato adicionado ao processo seletivo com sucesso.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Erro ao adicionar candidato.', 'details' => $e->getMessage()]);
} 