<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';

function normaliza($str) {
    return mb_strtolower(trim(preg_replace('/[^\w\s]/u', '', $str)));
}

try {
    if (!isset($_GET['vaga_id'])) {
        echo json_encode(['error' => 'vaga_id não informado']);
        exit;
    }
    $vaga_id = $_GET['vaga_id'];
    $pdo = Database::connect();

    // Buscar vaga
    $stmt = $pdo->prepare('SELECT * FROM vagas WHERE id = :id');
    $stmt->execute(['id' => $vaga_id]);
    $vaga = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vaga) throw new Exception('Vaga não encontrada');

    $palavras = array_filter(array_map('trim', explode(',', $vaga['keywords'] ?? '')));
    $palavras = array_map('normaliza', $palavras);
    $escolaridade_vaga = $vaga['escolaridade'] ?? '';

    // Buscar candidatos inscritos (dados brutos)
    $stmt = $pdo->prepare('
        SELECT c.id, c.nome, c.email, c.escolaridade, p.area_interesse
        FROM candidatos_vagas cv
        LEFT JOIN candidatos c ON c.email = cv.candidato_email
        LEFT JOIN preferencias p ON c.id = p.candidato_id
        WHERE cv.vaga_id = :vaga_id
    ');
    $stmt->execute(['vaga_id' => $vaga_id]);
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar experiências e qualificações para cada candidato (tudo em PHP)
    foreach ($candidatos as &$cand) {
        // Experiências
        $stmtExp = $pdo->prepare('SELECT cargo, responsabilidades FROM experiencias WHERE candidato_id = ?');
        $stmtExp->execute([$cand['id']]);
        $exps = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
        $cand['experiencias'] = implode('; ', array_map(function($e) {
            return $e['cargo'] . ': ' . $e['responsabilidades'];
        }, $exps));
        // Qualificações
        $stmtQual = $pdo->prepare('SELECT descricao, instituicao FROM qualificacoes WHERE candidato_id = ?');
        $stmtQual->execute([$cand['id']]);
        $quals = $stmtQual->fetchAll(PDO::FETCH_ASSOC);
        $cand['qualificacoes'] = implode('; ', array_map(function($q) {
            return $q['descricao'] . ': ' . $q['instituicao'];
        }, $quals));
    }

    // Comparativo de compatibilidade 100% em PHP
    $result = [];
    foreach ($candidatos as $cand) {
        $score = 0;
        $matches = [];
        $area = normaliza($cand['area_interesse'] ?? '');
        $exp = normaliza($cand['experiencias'] ?? '');
        $qual = normaliza($cand['qualificacoes'] ?? '');
        // Palavras-chave
        foreach ($palavras as $palavra) {
            if ($palavra && strpos($area, $palavra) !== false) {
                $score += 2;
                $matches[] = ["palavra"=>$palavra, "onde"=>"area_interesse", "tipo"=>"exato"];
            } elseif ($palavra && strpos($exp, $palavra) !== false) {
                $score += 1;
                $matches[] = ["palavra"=>$palavra, "onde"=>"experiencias", "tipo"=>"parcial"];
            } elseif ($palavra && strpos($qual, $palavra) !== false) {
                $score += 1;
                $matches[] = ["palavra"=>$palavra, "onde"=>"qualificacoes", "tipo"=>"parcial"];
            }
        }
        // Escolaridade
        $escolaridade_cand = $cand['escolaridade'] ?? '';
        $match_escolaridade = false;
        if ($escolaridade_vaga && $escolaridade_cand) {
            if (normaliza($escolaridade_vaga) === normaliza($escolaridade_cand)) {
                $score += 2;
                $match_escolaridade = true;
            }
        }
        $result[] = [
            'candidato_nome' => $cand['nome'] ?? '-',
            'candidato_email' => $cand['email'] ?? '-',
            'pontuacao' => $score,
            'match' => $matches,
            'match_escolaridade' => $match_escolaridade,
            'escolaridade_vaga' => $escolaridade_vaga,
            'escolaridade_candidato' => $escolaridade_cand,
            'area_interesse' => $cand['area_interesse'] ?? '',
            'experiencias' => $cand['experiencias'] ?? '',
            'qualificacoes' => $cand['qualificacoes'] ?? ''
        ];
    }
    usort($result, function($a, $b) { return $b['pontuacao'] <=> $a['pontuacao']; });
    echo json_encode([
        'debug_candidatos' => $candidatos,
        'compatibilidade' => $result
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 