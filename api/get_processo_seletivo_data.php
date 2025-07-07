<?php
// dash/api/get_processo_seletivo_data.php

header('Content-Type: application/json');
// Permitir CORS de origens específicas - ajuste conforme necessário
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    'https://formatarrh.athom.host', // Sua URL de produção
    'http://localhost',             // Desenvolvimento local
    'http://127.0.0.1',
    'https://formatar.com.br',
    'https://formatar.com.br/contrhol',
    'https://formatar.com.br/contrhol/dash'
];

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Se a origem não estiver na lista, você pode optar por não definir o header
    // ou definir um genérico, mas seja cauteloso com '*' em produção.
    // Para este exemplo, vamos permitir qualquer origem se não estiver na lista,
    // mas em um ambiente de produção, isso deve ser mais restrito.
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Adicionado Authorization se você usar tokens
header('Access-Control-Allow-Credentials: true'); // Se você precisar enviar cookies ou sessions

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuração do banco de dados (copiada de outros scripts)
$dbConfig = [
    'host' => '143.137.189.66',
    'port' => '7009',
    'user' => 'root',
    'password' => 'Formatar123', // Considere usar variáveis de ambiente para senhas
    'database' => 'formatarrh'
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Para melhor performance e segurança
        ]
    );

    // Parâmetro opcional para filtrar por vaga_id
    $vagaIdFiltro = isset($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : null;

    $sql = "
        SELECT
            cv.id_candidatura_vaga,
            cv.vaga_id,
            v.title AS vaga_titulo,
            v.company AS vaga_empresa,
            cv.candidato_email,
            c.id AS candidato_id_db, -- ID da tabela candidatos
            c.nome AS candidato_nome,
            cr.arquivo_caminho AS curriculo_arquivo, -- Nome do arquivo do currículo
            cv.status_selecao,
            cv.foi_entrevista,
            DATE_FORMAT(cv.data_inscricao, '%d/%m/%Y %H:%i') AS data_inscricao_formatada,
            DATE_FORMAT(cv.data_entrevista, '%d/%m/%Y %H:%i') AS data_entrevista_formatada,
            cv.data_entrevista, -- Manter data_entrevista original para edição
            cv.observacoes_entrevista,
            DATE_FORMAT(cv.atualizado_em, '%d/%m/%Y %H:%i') AS atualizado_em_formatado
        FROM candidatos_vagas cv
        JOIN vagas v ON cv.vaga_id = v.id
        JOIN candidatos c ON cv.candidato_email = c.email
        LEFT JOIN curriculos cr ON c.id = cr.candidato_id -- Assumindo que curriculos.candidato_id refere-se a candidatos.id
    ";

    $params = [];
    if ($vagaIdFiltro !== null) {
        $sql .= " WHERE cv.vaga_id = :vaga_id";
        $params[':vaga_id'] = $vagaIdFiltro;
    }

    $sql .= " ORDER BY v.title ASC, cv.data_inscricao DESC, c.nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll();

    // Estruturar os dados agrupados por vaga para facilitar o frontend
    $processosPorVaga = [];
    foreach ($resultados as $linha) {
        // Construir link do currículo (exemplo, ajuste o caminho base se necessário)
        // Assumindo que 'curriculos' está dentro de 'public' na raiz do servidor web.
        $linha['curriculo_link'] = null;
        if (!empty($linha['curriculo_arquivo'])) {
            // O ideal seria um endpoint dedicado: get_curriculo.php?file=nome_do_arquivo
            $linha['curriculo_link'] = '/public/curriculos/' . rawurlencode($linha['curriculo_arquivo']);
        }


        $processosPorVaga[$linha['vaga_id']]['vaga_info'] = [
            'id' => $linha['vaga_id'],
            'titulo' => $linha['vaga_titulo'],
            'empresa' => $linha['vaga_empresa']
        ];
        $processosPorVaga[$linha['vaga_id']]['candidatos'][] = [
            'id_candidatura_vaga' => $linha['id_candidatura_vaga'],
            'candidato_id_db' => $linha['candidato_id_db'],
            'candidato_nome' => $linha['candidato_nome'],
            'candidato_email' => $linha['candidato_email'],
            'curriculo_link' => $linha['curriculo_link'],
            'status_selecao' => $linha['status_selecao'],
            'foi_entrevista' => $linha['foi_entrevista'],
            'data_inscricao_formatada' => $linha['data_inscricao_formatada'],
            'data_entrevista_formatada' => $linha['data_entrevista_formatada'],
            'data_entrevista_original' => $linha['data_entrevista'], // Para preencher campos de data/hora
            'observacoes_entrevista' => $linha['observacoes_entrevista'],
            'atualizado_em_formatado' => $linha['atualizado_em_formatado']
        ];
    }

    // Reindexar para ser uma lista de vagas, não um mapa associativo por vaga_id
    echo json_encode(['success' => true, 'data' => array_values($processosPorVaga)]);

} catch (PDOException $e) {
    http_response_code(500);
    // Não exponha detalhes do erro em produção
    $errorMessage = 'Erro ao conectar ou executar consulta no banco de dados.';
    // error_log("Erro em get_processo_seletivo_data.php: " . $e->getMessage()); // Logar o erro real
    echo json_encode(['success' => false, 'message' => $errorMessage, 'debug_error' => $e->getMessage()]); // Apenas para debug
} catch (Exception $e) {
    http_response_code(500);
    // error_log("Erro geral em get_processo_seletivo_data.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado.', 'debug_error' => $e->getMessage()]); // Apenas para debug
}

?> 