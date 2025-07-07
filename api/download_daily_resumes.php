<?php
// Impedir qualquer saída antes dos headers
ob_start();

// Desabilitar exibição de erros no output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configurações de memória e tempo de execução
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300); // 5 minutos
set_time_limit(300);

error_log("Iniciando download_daily_resumes.php com configurações ajustadas");
error_log("Limite de memória: " . ini_get('memory_limit'));
error_log("Tempo máximo de execução: " . ini_get('max_execution_time'));

// Definir handler de erro personalizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Erro PHP [$errno]: $errstr em $errfile:$errline");
    return true;
});

// Handler para erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => 'Erro fatal no servidor',
            'details' => [
                'type' => 'fatal_error',
                'error_info' => $error
            ]
        ]);
    }
});

function checkPermissions() {
    error_log("Iniciando verificação de permissões");
    
    // Verificar diretório de currículos
    $curriculosDir = __DIR__ . '/../../public/curriculos/';
    error_log("Verificando diretório de currículos: " . $curriculosDir);
    
    if (!file_exists($curriculosDir)) {
        error_log("ERRO: Diretório de currículos não existe");
        throw new Exception("Diretório de currículos não existe: {$curriculosDir}");
    }
    
    if (!is_dir($curriculosDir)) {
        error_log("ERRO: O caminho não é um diretório");
        throw new Exception("O caminho não é um diretório: {$curriculosDir}");
    }
    
    if (!is_readable($curriculosDir)) {
        error_log("ERRO: Diretório de currículos não tem permissão de leitura");
        throw new Exception("Diretório de currículos não tem permissão de leitura: {$curriculosDir}");
    }
    
    // Verificar diretório temporário
    $tempDir = sys_get_temp_dir();
    error_log("Verificando diretório temporário: " . $tempDir);
    
    if (!is_dir($tempDir)) {
        error_log("ERRO: Diretório temporário não existe");
        throw new Exception("Diretório temporário não existe: {$tempDir}");
    }
    
    if (!is_writable($tempDir)) {
        error_log("ERRO: Diretório temporário não tem permissão de escrita");
        throw new Exception("Diretório temporário não tem permissão de escrita: {$tempDir}");
    }
    
    // Verificar se o PHP tem permissão para criar arquivos ZIP
    error_log("Verificando permissão para criar arquivos ZIP");
    $testZip = tempnam($tempDir, 'test_');
    if ($testZip === false) {
        error_log("ERRO: Não foi possível criar arquivo temporário de teste");
        throw new Exception("Não foi possível criar arquivo temporário de teste");
    }
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($testZip, ZipArchive::CREATE) !== true) {
            error_log("ERRO: Não foi possível criar arquivo ZIP de teste");
            throw new Exception("Não foi possível criar arquivo ZIP de teste");
        }
        $zip->addFromString('test.txt', 'Test content');
        $zip->close();
        error_log("Teste de criação de ZIP bem sucedido");
    } catch (Exception $e) {
        error_log("ERRO ao testar criação de ZIP: " . $e->getMessage());
        throw new Exception("Erro ao testar criação de ZIP: " . $e->getMessage());
    } finally {
        if (file_exists($testZip)) {
            unlink($testZip);
        }
    }
    
    // Verificar TCPDF
    error_log("Verificando TCPDF");
    $tcpdfPath = __DIR__ . '/../../lib/tcpdf/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        error_log("ERRO: TCPDF não encontrado");
        throw new Exception("TCPDF não encontrado em: {$tcpdfPath}");
    }
    if (!is_readable($tcpdfPath)) {
        error_log("ERRO: TCPDF não tem permissão de leitura");
        throw new Exception("TCPDF não tem permissão de leitura: {$tcpdfPath}");
    }
    
    // Verificar diretório de fontes do TCPDF
    $tcpdfFontsDir = __DIR__ . '/../../lib/tcpdf/fonts/';
    error_log("Verificando diretório de fontes do TCPDF: " . $tcpdfFontsDir);
    if (!is_dir($tcpdfFontsDir) || !is_readable($tcpdfFontsDir)) {
        error_log("ERRO: Diretório de fontes do TCPDF não existe ou não tem permissão de leitura");
        throw new Exception("Diretório de fontes do TCPDF não existe ou não tem permissão de leitura: {$tcpdfFontsDir}");
    }
    
    error_log("Todas as verificações de permissão passaram com sucesso");
    return true;
}

try {
    // Executar verificação de permissões
    checkPermissions();
    
    // Log da requisição
    error_log("Requisição recebida em download_daily_resumes.php");
    error_log("GET params: " . print_r($_GET, true));

    // Verificar se o TCPDF está disponível antes de qualquer outra coisa
    $tcpdfPath = __DIR__ . '/../../lib/tcpdf/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        throw new Exception("TCPDF não encontrado em: {$tcpdfPath}");
    }
    require_once $tcpdfPath;

    // Verificar se o diretório temporário é gravável
    $tempDir = sys_get_temp_dir();
    if (!is_writable($tempDir)) {
        throw new Exception("Diretório temporário não é gravável: {$tempDir}");
    }
    error_log("Diretório temporário é gravável: {$tempDir}");

    // Verificar se a extensão ZIP está instalada
    if (!extension_loaded('zip')) {
        throw new Exception("Extensão ZIP não está instalada no PHP");
    }
    error_log("Extensão ZIP está disponível");

    // Incluir configuração do banco de dados
    if (!defined('INCLUDED_FILE')) {
        define('INCLUDED_FILE', true);
    }
    require_once __DIR__ . '/../../config/database.php';

    // Conectar ao banco com PDO
    error_log("Tentando conectar ao banco de dados");
    try {
        $pdo = Database::connect();
        error_log("Conexão com o banco de dados estabelecida");
    } catch (Exception $e) {
        error_log("Erro ao conectar ao banco de dados: " . $e->getMessage());
        throw new Exception("Erro ao conectar ao banco de dados: " . $e->getMessage());
    }

    // Verificar parâmetros da requisição
    $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
    $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

    // Query para buscar os candidatos
    $query = "
        SELECT DISTINCT
            c.*,
            cu.arquivo_caminho AS curriculo,
            DATE_FORMAT(c.created_at, '%Y-%m-%d') as data,
            ct.telefone,
            ct.nascimento,
            ct.cep,
            ct.estado,
            ct.cidade,
            p.area_interesse,
            p.linkedin,
            p.pretensao_salarial,
            p.disponibilidade
        FROM candidatos c
        LEFT JOIN curriculos cu ON c.id = cu.candidato_id 
            AND cu.arquivo_caminho IS NOT NULL 
            AND cu.arquivo_caminho != ''
        LEFT JOIN contatos ct ON c.id = ct.candidato_id
        LEFT JOIN preferencias p ON c.id = p.candidato_id
        WHERE 1=1
        AND DATE(c.created_at) >= :data_inicio AND DATE(c.created_at) <= :data_fim";

    error_log("Query: " . $query);
    error_log("Parâmetros: " . print_r(['data_inicio' => $dataInicio, 'data_fim' => $dataFim], true));

    // Preparar e executar a query
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':data_inicio' => $dataInicio,
        ':data_fim' => $dataFim
    ]);

    // Buscar os resultados
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalCandidatos = count($candidatos);

    error_log("Total de candidatos encontrados: " . $totalCandidatos);

    if ($totalCandidatos === 0) {
        throw new Exception("Nenhum candidato encontrado para o período selecionado");
    }

    // Log dos candidatos encontrados
    foreach ($candidatos as $candidato) {
        error_log("Candidato: {$candidato['nome']} - Currículo: {$candidato['curriculo']}");
    }

    error_log("Total de candidatos a processar: " . $totalCandidatos);
    error_log("Memória em uso antes de criar ZIP: " . round(memory_get_usage() / 1024 / 1024) . "MB");

    // Adicionar headers CORS
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowed_origins = [
        'https://formatarrh.athom.host',
        'http://localhost',
        'http://127.0.0.1'
    ];

    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Criar arquivo ZIP temporário com extensão .zip
    $tempDir = sys_get_temp_dir();
    $zipFile = tempnam($tempDir, 'cur');
    if ($zipFile === false) {
        throw new Exception("Não foi possível criar arquivo temporário para o ZIP");
    }
    // Renomear para ter extensão .zip
    $zipFileWithExt = $zipFile . '.zip';
    if (file_exists($zipFileWithExt)) {
        unlink($zipFileWithExt);
    }
    rename($zipFile, $zipFileWithExt);
    $zipFile = $zipFileWithExt;
    
    error_log("Arquivo ZIP temporário criado: " . $zipFile);

    // Abrir arquivo ZIP
    $zip = new ZipArchive();
    $zipResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($zipResult !== true) {
        error_log("Erro ao criar arquivo ZIP. Código: " . $zipResult);
        throw new Exception("Não foi possível criar arquivo ZIP. Código: " . $zipResult);
    }
    error_log("Arquivo ZIP aberto com sucesso");

    // Diretório base dos currículos
    $curriculosDir = __DIR__ . '/../../public/curriculos/';
    error_log("Diretório base dos currículos: " . $curriculosDir);

    // Contador de arquivos adicionados
    $arquivosAdicionados = 0;
    $erros = [];

    // Processar cada candidato
    foreach ($candidatos as $candidato) {
        error_log("Processando candidato: {$candidato['nome']} (ID: {$candidato['id']})");

        // Criar nome seguro para a pasta
        $nomePasta = preg_replace('/[^a-zA-Z0-9]/', '_', remove_accents($candidato['nome']));
        
        // Gerar e adicionar a ficha
        try {
            $pdf = generateFichaPDF($candidato, $pdo);
            $fichaContent = $pdf->Output('', 'S');
            $nomeArquivoFicha = $nomePasta . '/ficha.pdf';
            
            if ($zip->addFromString($nomeArquivoFicha, $fichaContent)) {
                $arquivosAdicionados++;
                error_log("Ficha adicionada com sucesso para: {$candidato['nome']}");
            } else {
                $erros[] = "Erro ao adicionar ficha para {$candidato['nome']}";
                error_log("ERRO ao adicionar ficha para {$candidato['nome']}");
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao gerar ficha para {$candidato['nome']}: " . $e->getMessage();
            error_log("ERRO ao gerar/adicionar ficha para {$candidato['nome']}: " . $e->getMessage());
        }
        
        // Adicionar currículo se existir
        if (!empty($candidato['curriculo']) && $candidato['curriculo'] !== '#') {
            $curriculoNome = basename($candidato['curriculo']);
            $curriculoPath = $curriculosDir . $curriculoNome;
            
            error_log("Tentando acessar currículo em: {$curriculoPath}");
            
            if (file_exists($curriculoPath) && is_readable($curriculoPath)) {
                $nomeArquivoZip = $nomePasta . '/curriculo_' . $curriculoNome;
                
                try {
                    $curriculoContent = file_get_contents($curriculoPath);
                    if ($curriculoContent !== false) {
                        if ($zip->addFromString($nomeArquivoZip, $curriculoContent)) {
                            $arquivosAdicionados++;
                            error_log("Currículo adicionado com sucesso: {$curriculoPath}");
                        } else {
                            $erros[] = "Erro ao adicionar currículo para {$candidato['nome']}";
                            error_log("ERRO ao adicionar currículo para {$candidato['nome']}");
                        }
                    } else {
                        $erros[] = "Erro ao ler currículo para {$candidato['nome']}";
                        error_log("ERRO ao ler conteúdo do currículo: {$curriculoPath}");
                    }
                } catch (Exception $e) {
                    $erros[] = "Erro ao processar currículo para {$candidato['nome']}: " . $e->getMessage();
                    error_log("ERRO ao adicionar currículo para {$candidato['nome']}: " . $e->getMessage());
                }
            } else {
                $erros[] = "Currículo não encontrado para {$candidato['nome']}";
                error_log("ERRO: Currículo não encontrado ou não legível: {$curriculoPath}");
            }
        }
    }

    // Verificar se pelo menos um arquivo foi adicionado
    if ($arquivosAdicionados === 0) {
        throw new Exception("Nenhum arquivo foi adicionado ao ZIP. Erros encontrados: " . implode(", ", $erros));
    }

    // Fechar o arquivo ZIP
    if (!$zip->close()) {
        error_log("Erro ao finalizar o arquivo ZIP. Status: " . $zip->status);
        throw new Exception("Erro ao finalizar o arquivo ZIP. Status: " . $zip->status);
    }

    // Verificar se o arquivo foi criado e tem tamanho
    if (!file_exists($zipFile) || filesize($zipFile) === 0) {
        error_log("Arquivo ZIP não foi criado ou está vazio");
        throw new Exception("O arquivo ZIP não foi criado ou está vazio");
    }

    $fileSize = filesize($zipFile);
    error_log("Arquivo ZIP criado com sucesso. Tamanho: " . $fileSize . " bytes");

    // Verificar integridade do ZIP
    $testZip = new ZipArchive();
    $testResult = $testZip->open($zipFile);
    if ($testResult !== true) {
        error_log("Arquivo ZIP criado mas está corrompido. Código: " . $testResult);
        throw new Exception("O arquivo ZIP está corrompido. Código: " . $testResult);
    }
    $testZip->close();

    // Limpar qualquer saída anterior
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Headers para forçar o download
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="curriculos_' . date('Y-m-d_His') . '.zip"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);

    // Enviar arquivo
    if (!readfile($zipFile)) {
        error_log("Erro ao enviar arquivo ZIP");
        throw new Exception("Erro ao enviar arquivo ZIP");
    }

    // Remover arquivo temporário
    if (file_exists($zipFile)) {
        unlink($zipFile);
    }

    exit;

} catch (PDOException $e) {
    error_log("Erro PDO em download_daily_resumes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true, 
        'message' => 'Erro de banco de dados: ' . $e->getMessage(),
        'details' => [
            'type' => 'database_error',
            'debug_info' => error_get_last()
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro em download_daily_resumes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true, 
        'message' => $e->getMessage(),
        'details' => [
            'type' => 'general_error',
            'debug_info' => error_get_last(),
            'file_info' => $e->getFile() . ':' . $e->getLine()
        ]
    ]);
}

// Função para remover acentos
function remove_accents($string) {
    if (!preg_match('/[\x80-\xff]/', $string)) {
        return $string;
    }
    $chars = array(
        chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
        chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
        chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
        chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
        chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
        chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
        chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
        chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
        chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
        chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
        chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
        chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
        chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
        chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
        chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
        chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
        chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
        chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
        chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
        chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
        chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
        chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
        chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
        chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
        chr(195).chr(185) => 'u', chr(195).chr(186) => 'u',
        chr(195).chr(187) => 'u', chr(195).chr(188) => 'u',
        chr(195).chr(189) => 'y', chr(195).chr(191) => 'y'
    );
    return strtr($string, $chars);
}

// Funções auxiliares para o PDF
function addSection($pdf, $title) {
    global $corPrimaria, $corSecundaria;
    
    // Adiciona espaço antes da seção
    $pdf->Ln(8);
    
    // Configura a fonte e cor para o título
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($corPrimaria[0], $corPrimaria[1], $corPrimaria[2]);
    
    // Adiciona o título
    $pdf->Cell(0, 10, mb_strtoupper($title), 0, 1, 'L');
    
    // Adiciona linha decorativa abaixo do título
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor($corPrimaria[0], $corPrimaria[1], $corPrimaria[2]);
    $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 190, $pdf->GetY());
    
    // Reseta as cores e fonte para o conteúdo
    $pdf->SetTextColor($corSecundaria[0], $corSecundaria[1], $corSecundaria[2]);
    $pdf->SetFont('helvetica', '', 10);
    
    // Adiciona espaço depois do título
    $pdf->Ln(5);
}

function addField($pdf, $label, $value) {
    global $corPrimaria, $corSecundaria;
    
    // Configura a fonte e cor para o label
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor($corPrimaria[0], $corPrimaria[1], $corPrimaria[2]);
    
    // Calcula larguras
    $labelWidth = 60;
    $valueWidth = $pdf->GetPageWidth() - $labelWidth - 30; // 30 é a soma das margens
    
    // Adiciona o label
    $pdf->Cell($labelWidth, 8, $label . ':', 0, 0);
    
    // Configura a fonte e cor para o valor
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor($corSecundaria[0], $corSecundaria[1], $corSecundaria[2]);
    
    // Trata valor vazio
    $displayValue = $value ? $value : 'Não informado';
    
    // Adiciona o valor com fundo alternado
    static $alternate = false;
    if ($alternate) {
        $pdf->SetFillColor(247, 250, 252); // Azul muito claro para o fundo alternado
        $pdf->Cell($valueWidth, 8, $displayValue, 0, 1, 'L', true);
    } else {
        $pdf->Cell($valueWidth, 8, $displayValue, 0, 1, 'L', false);
    }
    $alternate = !$alternate;
}

// Função para gerar PDF da ficha
function generateFichaPDF($candidato, $pdo) {
    // Configurar TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configurações do documento
    $pdf->SetCreator('Formatar RH');
    $pdf->SetAuthor('Formatar RH');
    $pdf->SetTitle('Ficha do Candidato - ' . $candidato['nome']);
    
    // Remove cabeçalho e rodapé padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Define margens
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    // Adiciona a primeira página
    $pdf->AddPage();
    
    // Define as cores
    $corPrimaria = array(41, 128, 185); // Azul corporativo
    $corSecundaria = array(44, 62, 80); // Cinza azulado para textos
    
    // Cabeçalho moderno
    $pdf->SetFillColor($corPrimaria[0], $corPrimaria[1], $corPrimaria[2]);
    $pdf->RoundedRect(0, 0, $pdf->getPageWidth(), 40, 0, '1111', 'F');
    
    // Logo à direita
    $logoPath = __DIR__ . '/../../cropped-formatar-rh-1.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, $pdf->getPageWidth() - 60, 5, 45);
    }
    
    // Título e subtítulo
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(15, 8);
    $pdf->Cell(140, 12, 'FICHA DO CANDIDATO', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetXY(15, 22);
    $pdf->Cell(140, 8, 'Cadastrado em: ' . date('d/m/Y H:i', strtotime($candidato['created_at'])), 0, 1, 'L');
    
    // Reset posição e cores
    $pdf->SetY(50);
    $pdf->SetTextColor($corSecundaria[0], $corSecundaria[1], $corSecundaria[2]);
    
    // Informações Pessoais
    addSection($pdf, 'INFORMAÇÕES PESSOAIS');
    addField($pdf, 'Nome', $candidato['nome']);
    addField($pdf, 'E-mail', $candidato['email']);
    addField($pdf, 'Telefone', $candidato['telefone']);
    addField($pdf, 'Nascimento', $candidato['nascimento'] ? date('d/m/Y', strtotime($candidato['nascimento'])) : '-');
    addField($pdf, 'Endereço', $candidato['cidade'] . ' - ' . $candidato['estado'] . ' (CEP: ' . $candidato['cep'] . ')');
    
    // Documentos
    addSection($pdf, 'DOCUMENTOS');
    addField($pdf, 'RG', $candidato['rg']);
    addField($pdf, 'CPF', $candidato['cpf']);
    addField($pdf, 'CNH', $candidato['cnh']);
    
    // Formação
    addSection($pdf, 'FORMAÇÃO ACADÊMICA');
    addField($pdf, 'Escolaridade', $candidato['escolaridade']);
    addField($pdf, 'Status', $candidato['status_escolaridade']);
    
    // Preferências
    addSection($pdf, 'PREFERÊNCIAS PROFISSIONAIS');
    addField($pdf, 'Área', $candidato['area_interesse']);
    if ($candidato['linkedin']) {
        addField($pdf, 'LinkedIn', $candidato['linkedin']);
    }
    addField($pdf, 'Pretensão', $candidato['pretensao_salarial']);
    addField($pdf, 'Disponível', $candidato['disponibilidade']);
    
    // Experiências
    $query = "SELECT cargo, empresa, inicio, termino, responsabilidades FROM experiencias WHERE candidato_id = :id ORDER BY inicio DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $candidato['id']]);
    $experiencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($experiencias)) {
        addSection($pdf, 'EXPERIÊNCIA PROFISSIONAL');
        foreach ($experiencias as $exp) {
            $periodo = date('m/Y', strtotime($exp['inicio'])) . ' - ';
            $periodo .= $exp['termino'] ? date('m/Y', strtotime($exp['termino'])) : 'Atual';
            
            // Cargo e empresa
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor($corPrimaria[0], $corPrimaria[1], $corPrimaria[2]);
            $pdf->Cell(0, 8, $exp['cargo'], 0, 1, 'L');
            
            // Empresa e período
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 6, $exp['empresa'] . ' | ' . $periodo, 0, 1, 'L');
            
            // Responsabilidades
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor($corSecundaria[0], $corSecundaria[1], $corSecundaria[2]);
            $pdf->MultiCell(0, 6, $exp['responsabilidades'], 0, 'L');
            $pdf->Ln(3);
        }
    }
    
    // Qualificações
    $query = "SELECT descricao, instituicao, ano_conclusao FROM qualificacoes WHERE candidato_id = :id ORDER BY ano_conclusao DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $candidato['id']]);
    $qualificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($qualificacoes)) {
        addSection($pdf, 'QUALIFICAÇÕES ADICIONAIS');
        foreach ($qualificacoes as $qual) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor($corPrimaria[0], $corPrimaria[1], $corPrimaria[2]);
            $pdf->Cell(0, 7, $qual['descricao'], 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(100, 100, 100);
            $info = $qual['instituicao'];
            if ($qual['ano_conclusao']) {
                $info .= ' (' . date('Y', strtotime($qual['ano_conclusao'])) . ')';
            }
            $pdf->Cell(0, 5, $info, 0, 1, 'L');
            $pdf->Ln(2);
        }
    }
    
    // Rodapé
    $pdf->SetY(-20);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 10, 'Documento gerado automaticamente pelo sistema Formatar RH - ' . date('d/m/Y H:i:s'), 0, 0, 'C');
    
    return $pdf;
} 