<?php
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' - FATAL: ' . print_r($error, true) . "\n", FILE_APPEND);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erro fatal: ' . $error['message'];
    }
});

header('Content-Type: application/pdf; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

function separador($pdf) {
    $y = $pdf->GetY() + 4;
    $pdf->SetDrawColor(37, 99, 235);
    $pdf->SetLineWidth(1.8);
    $pdf->Line(15, $y, $pdf->getPageWidth() - 15, $y);
    $pdf->Ln(12);
}

$email = isset($_GET['email']) ? trim($_GET['email']) : null;
if (!$email) {
    http_response_code(400);
    echo 'E-mail não informado.';
    exit;
}

try {
    $pdo = Database::connect();


    $stmt = $pdo->prepare('SELECT * FROM candidatos WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $candidato = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$candidato) {
        echo 'Candidato não encontrado.';
        exit;
    }
    $candidato_id = $candidato['id'];

    // Contato
    $stmt = $pdo->prepare('SELECT * FROM contatos WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $contato = $stmt->fetch(PDO::FETCH_ASSOC);
    $contato = $contato ?: [];

    // Preferências
    $stmt = $pdo->prepare('SELECT * FROM preferencias WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $preferencias = $stmt->fetch(PDO::FETCH_ASSOC);
    $preferencias = $preferencias ?: [];

    // Qualificações
    $stmt = $pdo->prepare('SELECT descricao, instituicao, ano_conclusao FROM qualificacoes WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $qualificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $qualificacoes = $qualificacoes ?: [];

    // Experiências
    $stmt = $pdo->prepare('SELECT cargo, empresa, inicio, termino, responsabilidades FROM experiencias WHERE candidato_id = :cid');
    $stmt->execute([':cid' => $candidato_id]);
    $experiencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $experiencias = $experiencias ?: [];

    // Vagas inscritas
    $stmt = $pdo->prepare('SELECT v.title, v.company, v.description, cv.data_inscricao FROM candidatos_vagas cv JOIN vagas v ON cv.vaga_id = v.id WHERE cv.candidato_email = :email');
    $stmt->execute([':email' => $email]);
    $vagas_inscritas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $vagas_inscritas = $vagas_inscritas ?: [];

    // Caminho da logo
    $logo1 = realpath(__DIR__ . '/../cropped-formatar-rh-1.png');

    class CustomPDF extends TCPDF {
        function Header() {
            if ($this->PageNo() == 1) {
                $this->SetFillColor(37, 99, 235); // #2563eb
                $this->Rect(0, 0, $this->getPageWidth(), 40, 'F');
            }
            // Não exibe cabeçalho nas demais páginas
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Gerado por Formatar RH - ' . date('d/m/Y H:i'), 0, 0, 'C');
        }
    }

    $pdf = new CustomPDF();
    $pdf->SetCreator('Formatar RH');
    $pdf->SetAuthor('Formatar RH');
    $pdf->SetTitle('Ficha do Candidato');
    $pdf->SetMargins(15, 48, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 14, 'Ficha do Candidato', 0, 1, 'C');
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica', '', 12);
    separador($pdf);

    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 10, 'Dados Pessoais', 0, 1, 'L');
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica', '', 11);
    // Layout de tabela de formulário elegante
    $html = '<table cellpadding="6" cellspacing="0" style="width:100%;font-size:11px;margin-bottom:14px;border-collapse:collapse;">';
    $fields = [
        ['Nome', htmlspecialchars($candidato['nome'])],
        ['E-mail', htmlspecialchars($candidato['email'])],
        ['Telefone', htmlspecialchars($contato['telefone'] ?? '-')],
        ['Data de Nascimento', (!empty($contato['nascimento']) ? date('d/m/Y', strtotime($contato['nascimento'])) : '-')],
        ['Cidade', htmlspecialchars($contato['cidade'] ?? '-')],
        ['Estado', htmlspecialchars($contato['estado'] ?? '-')],
        ['Escolaridade', htmlspecialchars($candidato['escolaridade'] ?? '-')],
        ['Status Escolaridade', htmlspecialchars($candidato['status_escolaridade'] ?? '-')],
        ['Área de Interesse', htmlspecialchars($preferencias['area_interesse'] ?? '-')],
        ['LinkedIn', htmlspecialchars($preferencias['linkedin'] ?? '-')],
        ['Pretensão Salarial', htmlspecialchars($preferencias['pretensao_salarial'] ?? '-')],
        ['Disponibilidade', htmlspecialchars($preferencias['disponibilidade'] ?? '-')],
        ['LGPD', (!empty($candidato['lgpd']) ? 'Aceito' : 'Não aceito')],
    ];
    foreach ($fields as $i => $f) {
        $bg = $i % 2 == 0 ? '#f3f4f6' : '#fff';
        $html .= '<tr style="background:' . $bg . ';">';
        $html .= '<td style="width:38%;border:1px solid #e5e7eb;text-align:left;padding-left:10px;"><b>' . $f[0] . ':</b></td>';
        $html .= '<td style="width:62%;border:1px solid #e5e7eb;text-align:left;padding-left:10px;">' . $f[1] . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    separador($pdf);

    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 10, 'Qualificações', 0, 1, 'L');
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica', '', 11);
    $html = '<table cellpadding="6" cellspacing="0" style="width:100%;font-size:11px;margin-bottom:14px;border-collapse:collapse;">';
    $html .= '<tr style="background:#e5e7eb;"><td style="width:50%;border:1px solid #e5e7eb;padding-left:10px;"><b>Descrição</b></td><td style="width:30%;border:1px solid #e5e7eb;padding-left:10px;"><b>Instituição</b></td><td style="width:20%;border:1px solid #e5e7eb;padding-left:10px;"><b>Ano</b></td></tr>';
    if (count($qualificacoes) > 0) {
        foreach ($qualificacoes as $i => $q) {
            $bg = $i % 2 == 0 ? '#f3f4f6' : '#fff';
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . htmlspecialchars($q['descricao']) . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . htmlspecialchars($q['instituicao']) . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . htmlspecialchars($q['ano_conclusao']) . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="3" style="border:1px solid #e5e7eb;text-align:center;color:#888;background:#f3f4f6;">Nenhuma qualificação registrada.</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    separador($pdf);

    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 10, 'Histórico Profissional', 0, 1, 'L');
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica', '', 11);
    $html = '<table cellpadding="6" cellspacing="0" style="width:100%;font-size:11px;margin-bottom:14px;border-collapse:collapse;">';
    $html .= '<tr style="background:#e5e7eb;"><td style="width:30%;border:1px solid #e5e7eb;padding-left:10px;"><b>Cargo</b></td><td style="width:30%;border:1px solid #e5e7eb;padding-left:10px;"><b>Empresa</b></td><td style="width:20%;border:1px solid #e5e7eb;padding-left:10px;"><b>Período</b></td><td style="width:20%;border:1px solid #e5e7eb;padding-left:10px;"><b>Responsabilidades</b></td></tr>';
    if (count($experiencias) > 0) {
        foreach ($experiencias as $i => $e) {
            $bg = $i % 2 == 0 ? '#f3f4f6' : '#fff';
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . htmlspecialchars($e['cargo']) . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . htmlspecialchars($e['empresa']) . '</td>';
            $periodo = (!empty($e['inicio']) ? date('m/Y', strtotime($e['inicio'])) : '-') . ' a ' . (!empty($e['termino']) && $e['termino'] !== '1900-01-01' ? date('m/Y', strtotime($e['termino'])) : 'Atualmente');
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . $periodo . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . (!empty($e['responsabilidades']) ? htmlspecialchars($e['responsabilidades']) : '-') . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="4" style="border:1px solid #e5e7eb;text-align:center;color:#888;background:#f3f4f6;">Nenhuma experiência registrada.</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    separador($pdf);

    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 10, 'Vagas Inscritas', 0, 1, 'L');
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica', '', 11);
    $html = '<table cellpadding="6" cellspacing="0" style="width:100%;font-size:11px;margin-bottom:14px;border-collapse:collapse;">';
    $html .= '<tr style="background:#e5e7eb;"><td style="width:30%;border:1px solid #e5e7eb;padding-left:10px;"><b>Título</b></td><td style="width:30%;border:1px solid #e5e7eb;padding-left:10px;"><b>Empresa</b></td><td style="width:20%;border:1px solid #e5e7eb;padding-left:10px;"><b>Inscrito em</b></td><td style="width:20%;border:1px solid #e5e7eb;padding-left:10px;"><b>Descrição</b></td></tr>';
    if (count($vagas_inscritas) > 0) {
        foreach ($vagas_inscritas as $i => $v) {
            $bg = $i % 2 == 0 ? '#f3f4f6' : '#fff';
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . htmlspecialchars($v['title']) . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . htmlspecialchars($v['company']) . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . (!empty($v['data_inscricao']) ? date('d/m/Y', strtotime($v['data_inscricao'])) : '-') . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding-left:10px;">' . (!empty($v['description']) ? htmlspecialchars($v['description']) : '-') . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="4" style="border:1px solid #e5e7eb;text-align:center;color:#888;background:#f3f4f6;">Nenhuma inscrição encontrada.</td></tr>';
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $pdf->Output('Ficha_' . preg_replace('/[^a-z0-9]/i', '_', $candidato['nome']) . '.pdf', 'I');
    exit;

} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", FILE_APPEND);
    echo 'Erro ao gerar ficha: ' . $e->getMessage();
    exit;
} 