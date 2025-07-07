<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$data = isset($_GET['data']) ? $_GET['data'] : null;
if (!$data) {
    echo json_encode(['error' => true, 'message' => 'Data não informada.']);
    exit;
}
try {
    $pdo = Database::connect();
    // Suporta tanto DATETIME quanto VARCHAR no formato 'aaaa-mm-dd hh:mm'
    $stmt = $pdo->prepare('
        SELECT 
            LPAD(HOUR(
                IF(LOCATE(":", created_at) > 0, 
                    STR_TO_DATE(created_at, "%Y-%m-%d %H:%i"), 
                    created_at
                )
            ), 2, "0") as hora, 
            COUNT(*) as quantidade
        FROM candidatos
        WHERE DATE(
            IF(LOCATE(":", created_at) > 0, 
                STR_TO_DATE(created_at, "%Y-%m-%d %H:%i"), 
                created_at
            )
        ) = :data
        GROUP BY hora
        ORDER BY hora
    ');
    $stmt->execute([':data' => $data]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Garante todas as horas no resultado
    $horas = array_fill(0, 24, 0);
    foreach ($result as $row) {
        $horas[(int)$row['hora']] = (int)$row['quantidade'];
    }
    $saida = [];
    for ($h = 0; $h < 24; $h++) {
        $saida[] = [
            'hora' => str_pad($h, 2, '0', STR_PAD_LEFT),
            'quantidade' => $horas[$h]
        ];
    }
    echo json_encode($saida);
} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    exit;
} 