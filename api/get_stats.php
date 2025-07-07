<?php

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

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_log("Iniciando get_stats.php");

$dbConfig = [
    'host' => '143.137.189.66',
    'port' => '7009',
    'user' => 'root',
    'password' => 'Formatar123',
    'database' => 'formatarrh'
];

try {
    error_log("Tentando conectar ao banco de dados");
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
    $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

    $sql = "SELECT COUNT(*) as total FROM candidatos";
    $stmt = $pdo->query($sql);
    $totalCurriculos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $sql = "SELECT COUNT(*) as hoje FROM candidatos WHERE DATE(created_at) = CURRENT_DATE";
    $stmt = $pdo->query($sql);
    $curriculosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];

    $sql = "SELECT COUNT(*) as ontem FROM candidatos WHERE DATE(created_at) = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
    $stmt = $pdo->query($sql);
    $curriculosOntem = $stmt->fetch(PDO::FETCH_ASSOC)['ontem'];

    $variacaoPercentual = 0;
    if ($curriculosOntem > 0) {
        $variacaoPercentual = (($curriculosHoje - $curriculosOntem) / $curriculosOntem) * 100;
    }

    $stats = [
        'total_curriculos' => $totalCurriculos,
        'curriculos_hoje' => $curriculosHoje,
        'curriculos_ontem' => $curriculosOntem,
        'variacao_percentual' => round($variacaoPercentual, 1)
    ];

    $query = "
        SELECT 
            DATE(created_at) as data,
            COUNT(*) as total
        FROM candidatos
        WHERE DATE(created_at) BETWEEN :data_inicio AND :data_fim
        GROUP BY DATE(created_at)
        ORDER BY data
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim
    ]);
    $curriculos_por_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "
        SELECT 
            p.area_interesse,
            COUNT(*) as total
        FROM candidatos c
        JOIN preferencias p ON c.id = p.candidato_id
        WHERE DATE(c.created_at) BETWEEN :data_inicio AND :data_fim
        GROUP BY p.area_interesse
        ORDER BY total DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim
    ]);
    $areas_interesse = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "
        SELECT 
            title,
            company,
            date_fechamento,
            candidato
        FROM vagas
        WHERE status = 'Fechada'
        AND date_fechamento BETWEEN :data_inicio AND :data_fim
        AND candidato IS NOT NULL
        ORDER BY date_fechamento DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim
    ]);
    $vagas_fechadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "
        SELECT 
            COUNT(*) as total_vagas,
            COUNT(CASE WHEN status = 'Em Divulgação' THEN 1 END) as vagas_abertas,
            COUNT(CASE WHEN status = 'Fechada' THEN 1 END) as vagas_fechadas,
            COUNT(CASE WHEN status = 'Suspensa' THEN 1 END) as vagas_suspensas,
            COUNT(CASE WHEN status = 'Aguardando Retorno' THEN 1 END) as vagas_aguardando,
            SUM(CASE WHEN status = 'Em Divulgação' THEN quantidade ELSE 0 END) as total_posicoes_abertas
        FROM vagas
    ";
    error_log("Executando query de estatísticas de vagas: " . $query);
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stats_vagas = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Resultado da query de vagas: " . json_encode($stats_vagas));

    $query = "
        SELECT 
            AVG(DATEDIFF(date_fechamento, created_at)) as media_dias_fechamento
        FROM vagas
        WHERE status = 'Fechada'
        AND date_fechamento BETWEEN :data_inicio AND :data_fim
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim
    ]);
    $media_fechamento = $stmt->fetch(PDO::FETCH_ASSOC);

    $query = "
        WITH RECURSIVE
        split_keywords AS (
            SELECT 
                id,
                SUBSTRING_INDEX(keywords, ',', 1) as keyword
            FROM vagas
            WHERE keywords IS NOT NULL
            UNION ALL
            SELECT 
                id,
                SUBSTRING_INDEX(SUBSTRING(keywords, LENGTH(SUBSTRING_INDEX(keywords, ',', n.n)) + 2), ',', 1) as keyword
            FROM vagas
            CROSS JOIN (
                SELECT a.N + b.N * 10 + 1 as n
                FROM (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
                CROSS JOIN (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                ORDER BY n
            ) n
            WHERE n.n <= LENGTH(keywords) - LENGTH(REPLACE(keywords, ',', '')) + 1
            AND LENGTH(SUBSTRING_INDEX(SUBSTRING(keywords, LENGTH(SUBSTRING_INDEX(keywords, ',', n.n)) + 2), ',', 1)) > 0
        )
        SELECT 
            TRIM(keyword) as area_principal,
            COUNT(DISTINCT id) as total
        FROM split_keywords
        WHERE keyword != ''
        GROUP BY TRIM(keyword)
        ORDER BY total DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $top_areas_vagas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mes,
            COUNT(*) as total_vagas,
            COUNT(CASE WHEN status = 'Fechada' THEN 1 END) as vagas_fechadas
        FROM vagas
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mes
        ORDER BY mes
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $vagas_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Dados obtidos: " . json_encode([
        'stats' => $stats,
        'stats_vagas' => $stats_vagas
    ]));

    echo json_encode([
        'stats' => $stats,
        'curriculos_por_dia' => $curriculos_por_dia,
        'areas_interesse' => $areas_interesse,
        'vagas_fechadas' => $vagas_fechadas,
        'stats_vagas' => $stats_vagas,
        'media_fechamento' => $media_fechamento,
        'top_areas_vagas' => $top_areas_vagas,
        'vagas_por_mes' => $vagas_por_mes
    ]);

} catch (Exception $e) {
    error_log("Erro em get_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()]);
}
?> 