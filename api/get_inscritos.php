<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$dbConfig = [
    'host' => '143.137.189.66',
    'port' => '7009',
    'user' => 'root',
    'password' => 'Formatar123',
    'database' => 'formatarrh'
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if (!isset($_GET['vaga_id'])) {
        echo json_encode([]);
        exit;
    }
    $vaga_id = $_GET['vaga_id'];

    $stmt = $pdo->prepare('
        SELECT 
          cv.candidato_email, 
          c.nome AS candidato_nome, 
          cu.arquivo_caminho AS curriculo_link,
          cv.status_selecao, 
          cv.data_inscricao, 
          cv.data_entrevista, 
          cv.observacoes_entrevista
        FROM candidatos_vagas cv
        LEFT JOIN candidatos c ON c.email = cv.candidato_email
        LEFT JOIN curriculos cu ON cu.candidato_id = c.id
        WHERE cv.vaga_id = :vaga_id
        ORDER BY cv.data_inscricao DESC
    ');
    $stmt->execute(['vaga_id' => $vaga_id]);
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajustar o caminho do currículo para download, se existir
    foreach ($candidatos as &$cand) {
        if (!empty($cand['curriculo_link'])) {
            $cand['curriculo_link'] = '/public/curriculos/' . rawurlencode(basename($cand['curriculo_link']));
        } else {
            $cand['curriculo_link'] = null;
        }
    }

    echo json_encode($candidatos);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>