<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['error' => true, 'message' => 'Método não permitido']);
    exit;
}

$email = isset($_GET['email']) ? trim($_GET['email']) : null;
if (!$email) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['error' => true, 'message' => 'E-mail não informado.']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = Database::connect();
    
    $stmt = $pdo->prepare('SELECT * FROM candidatos WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $candidato = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$candidato) {
        echo json_encode(['error' => true, 'message' => 'Candidato não encontrado.']);
        exit;
    }
    $candidato_id = $candidato['id'];

    $stmt = $pdo->prepare('SELECT * FROM contatos WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $contato = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM preferencias WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $preferencias = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT descricao, instituicao, ano_conclusao FROM qualificacoes WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $qualificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT cargo, empresa, inicio, termino, responsabilidades FROM experiencias WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $experiencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT v.id, v.title, v.company, cv.data_inscricao FROM candidatos_vagas cv JOIN vagas v ON cv.vaga_id = v.id WHERE cv.candidato_email = :email');
    $stmt->execute([':email' => $email]);
    $vagas_inscritas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'candidato' => $candidato,
        'contato' => $contato,
        'preferencias' => $preferencias,
        'qualificacoes' => $qualificacoes,
        'experiencias' => $experiencias,
        'vagas_inscritas' => $vagas_inscritas
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['error' => true, 'message' => 'Erro ao buscar detalhes do candidato.', 'details' => $e->getMessage()]);
} 