<?php
require_once 'config.php';

global $DB_LOCAL_MV;

// Tenta carregar via autoload do composer se existir
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Verifica se dompdf está disponível
if (!class_exists('Dompdf\Dompdf')) {
    http_response_code(500);
    die('Dompdf não está instalado. Execute: composer install ou composer require dompdf/dompdf');
}

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/pdf; charset=utf-8');

$tarefa = isset($_GET['tarefa']) ? trim($_GET['tarefa']) : '';
if ($tarefa === '') {
    http_response_code(400);
    die('Parâmetro inválido: tarefa');
}

function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dashIfEmpty($v) {
    $s = trim((string)($v ?? ''));
    return $s === '' ? '—' : $s;
}

function nl2br_safe($v) {
    $s = trim((string)($v ?? ''));
    if ($s === '') return '—';
    return nl2br(h($s));
}

function formatDateBR($v) {
    $s = trim((string)($v ?? ''));
    if ($s === '') return '';
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) return $s;
    try {
        $dt = new DateTime($s);
        return $dt->format('d/m/Y');
    } catch (Exception $e) {
        return $s;
    }
}

function formatHour($v) {
    $s = trim((string)($v ?? ''));
    if ($s === '') return '';
    if (preg_match('/^(\d{2}):(\d{2})/', $s, $m)) {
        return $m[1] . ':' . $m[2];
    }
    return $s;
}

function formatDateTimeBR($date, $hour) {
    $d = formatDateBR($date);
    $h = formatHour($hour);
    $out = trim(($d ? $d : '') . ($h ? ' ' . $h : ''));
    return $out === '' ? '—' : $out;
}

function parseEndereco($localizacao) {
    $localizacao = (string)($localizacao ?? '');
    $rua = '';
    $numero = '';

    if (preg_match('/END:\s*([^,]+?),\s*(\d+)/i', $localizacao, $matches)) {
        $rua = trim($matches[1] ?? '');
        $numero = trim($matches[2] ?? '');
    } elseif (preg_match('/END:\s*([A-ZÁÉÍÓÚÇÃÕ\s]+?)\s+(\d+)/iu', $localizacao, $matches)) {
        $rua = trim($matches[1] ?? '');
        $numero = trim($matches[2] ?? '');
    } elseif (preg_match('/END:\s*([^,|-]+)/i', $localizacao, $matches)) {
        $enderecoCompleto = trim($matches[1] ?? '');
        if (preg_match('/^(.+?)\s+(\d+)$/u', $enderecoCompleto, $matches2)) {
            $rua = trim($matches2[1] ?? '');
            $numero = trim($matches2[2] ?? '');
        } else {
            $rua = $enderecoCompleto;
        }
    }

    return [$rua, $numero];
}

function extractUrls($raw) {
    $s = trim((string)($raw ?? ''));
    if ($s === '') return [];
    $parts = preg_split('/\s+/', $s);
    $urls = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $p = rtrim($p, ",.;");
        if (!preg_match('#^https?://#i', $p)) continue;
        $urls[$p] = true;
    }
    return array_keys($urls);
}

function isImagePortrait($imageUrl) {
    // Tenta detectar se a imagem é vertical (portrait)
    // Retorna true se altura > largura
    try {
        $headers = @get_headers($imageUrl, 1);
        if (!$headers || strpos($headers[0], '200') === false) {
            return false;
        }
        
        // Tenta obter dimensões da imagem
        $imageInfo = @getimagesize($imageUrl);
        if ($imageInfo && isset($imageInfo[0]) && isset($imageInfo[1])) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            return $height > $width;
        }
    } catch (Exception $e) {
        // Em caso de erro, assume que não é portrait
    }
    return false;
}

function renderFotosFromUrls($urls, $imgClass = '') {
    if (!is_array($urls) || count($urls) === 0) return '<div style="color:#444;">—</div>';
    $imgs = '';
    $cls = trim((string)$imgClass);
    foreach ($urls as $u) {
        $isPortrait = isImagePortrait($u);
        $clsFinal = 'foto-img' . ($cls !== '' ? ' ' . $cls : '') . ($isPortrait ? ' portrait' : '');
        $clsAttr = ' class="' . h($clsFinal) . '"';
        $imgs .= '<img src="' . h($u) . '" alt="foto"' . $clsAttr . ' style="display:block;margin:10px auto;border:1px solid #000;page-break-inside:avoid;page-break-after:avoid;">';
    }
    return '<div class="block-photos" style="page-break-inside:avoid;">' . $imgs . '</div>';
}

function renderFotosExecucaoPaginas($urls) {
    if (!is_array($urls) || count($urls) === 0) return '';
    
    $totalFotos = count($urls);
    $html = '';
    
    // Divide as fotos em grupos de 1-2 fotos por página
    $fotoIndex = 0;
    $paginaNum = 0;
    
    while ($fotoIndex < $totalFotos) {
        $paginaNum++;
        $fotosNestaPagina = [];
        
        // Pega 1 ou 2 fotos para esta página
        // Se é a última foto ou se já pegou 2, pega apenas 1
        // Caso contrário, pega 2
        if ($fotoIndex === $totalFotos - 1) {
            // Última foto, pega apenas 1
            $fotosNestaPagina[] = $urls[$fotoIndex];
            $fotoIndex++;
        } else {
            // Pega 2 fotos
            $fotosNestaPagina[] = $urls[$fotoIndex];
            $fotoIndex++;
            if ($fotoIndex < $totalFotos) {
                $fotosNestaPagina[] = $urls[$fotoIndex];
                $fotoIndex++;
            }
        }
        
        // Renderiza as fotos desta página
        $imgs = '';
        foreach ($fotosNestaPagina as $u) {
            $isPortrait = isImagePortrait($u);
            $clsFinal = 'foto-img' . ($isPortrait ? ' portrait' : '');
            $clsAttr = ' class="' . h($clsFinal) . '"';
            $imgs .= '<img src="' . h($u) . '" alt="foto"' . $clsAttr . ' style="display:block;margin:10px auto;border:1px solid #000;page-break-inside:avoid;page-break-after:avoid;margin-left:auto;margin-right:auto;text-align:center;">';
        }
        
        // Adiciona quebra de página antes (exceto na primeira página)
        $pageBreakStyle = ($paginaNum > 1) ? 'page-break-before:always;' : '';
        
        $html .= '<table style="' . $pageBreakStyle . 'page-break-inside:avoid;">
<tr><td class="section">Fotos Durante a Execução</td></tr>
<tr><td class="photos" style="text-align:center;">' . $imgs . '</td></tr>
</table>';
    }
    
    return $html;
}

function renderFotosHtml($raw) {
    $s = trim((string)($raw ?? ''));
    if ($s === '') return '<div style="color:#444;">—</div>';
    $urls = extractUrls($s);
    return renderFotosFromUrls($urls);
}

function filterOutUrls($raw, $urlsToRemove) {
    $urls = extractUrls($raw);
    if (!is_array($urlsToRemove) || count($urlsToRemove) === 0) return $urls;
    $set = array_fill_keys($urlsToRemove, true);
    return array_values(array_filter($urls, function($u) use ($set) {
        return !isset($set[$u]);
    }));
}

function renderMateriaisBoxHtml($materialPrincipal, $pavimentacao, $itens) {
    $mp = trim((string)($materialPrincipal ?? ''));
    $pv = trim((string)($pavimentacao ?? ''));

    $top = '';
    if ($mp !== '' || $pv !== '') {
        $top .= '<div style="margin-bottom:6px;font-size:9px;">';
        if ($mp !== '') $top .= '<div><strong>Material principal:</strong> ' . h($mp) . '</div>';
        if ($pv !== '') $top .= '<div><strong>Pavimentação:</strong> ' . h($pv) . '</div>';
        $top .= '</div>';
    }

    return $top . renderMateriaisHtml($itens);
}

function renderMateriaisHtml($itens) {
    if (!is_array($itens) || count($itens) === 0) {
        return '<div style="color:#444;">—</div>';
    }

    $total = 0.0;
    $rows = '';
    foreach ($itens as $it) {
        $mat = h($it['material'] ?? '');
        $un = h($it['unidade'] ?? '');
        $qtd = (float)($it['quantidade'] ?? 0);
        $val = (float)($it['valor_total'] ?? 0);
        $total += $val;

        $rows .= '<tr>'
            . '<td style="border:1px solid #000;padding:4px;">' . $mat . '</td>'
            . '<td style="border:1px solid #000;padding:4px;white-space:nowrap;">' . $un . '</td>'
            . '<td style="border:1px solid #000;padding:4px;white-space:nowrap;text-align:right;">' . h(number_format($qtd, 4, ',', '.')) . '</td>'
            . '<td style="border:1px solid #000;padding:4px;white-space:nowrap;text-align:right;">R$ ' . h(number_format($val, 2, ',', '.')) . '</td>'
            . '</tr>';
    }

    $rows .= '<tr>'
        . '<td colspan="3" style="border:1px solid #000;padding:4px;text-align:right;font-weight:bold;">Total</td>'
        . '<td style="border:1px solid #000;padding:4px;white-space:nowrap;text-align:right;font-weight:bold;">R$ ' . h(number_format($total, 2, ',', '.')) . '</td>'
        . '</tr>';

    return '<table style="width:100%;border-collapse:collapse;font-size:9px;">'
        . '<thead><tr>'
        . '<th style="border:1px solid #000;padding:4px;text-align:left;">Material</th>'
        . '<th style="border:1px solid #000;padding:4px;text-align:left;">UN</th>'
        . '<th style="border:1px solid #000;padding:4px;text-align:right;">Quantidade</th>'
        . '<th style="border:1px solid #000;padding:4px;text-align:right;">Valor</th>'
        . '</tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '</table>';
}

try {
    $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $cidadeWhere = getCidadeWhereCondition('l');
    $cidadeWhereSql = $cidadeWhere ? " AND $cidadeWhere" : "";

    $sql = "
        SELECT
            t.tsk_id AS tarefa,
            a.age_name AS quem,
            l.loc_integrationid AS ss,
            l.loc_description AS localizacao,
            l.e_localidade AS cidade,
            l.e_bairro AS bairro,
            l.e_setor AS setor,
            TO_CHAR(t.tsk_datetimeinsert::date, 'DD/MM/YYYY') AS dt_registro,
            TO_CHAR(l.e_dataregistro::date, 'DD/MM/YYYY') AS dt_recepcao,
            tt.tty_description AS servico,
            l.e_reflocalizacao AS ref_localizacao,
            l.e_informacaosolicitante AS informacao_solicitante,
            l.e_esclarecimentosolicitante AS esclarecimento_solicitante
        FROM u45468.task AS t
        INNER JOIN {$DB_LOCAL_MV} AS l ON l.loc_id = t.loc_id
        LEFT JOIN u45468.agent AS a ON t.age_id = a.age_id
        INNER JOIN u45468.tasktype AS tt ON tt.tty_id = t.tty_id
        WHERE t.tsk_id = :tarefa
        $cidadeWhereSql
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':tarefa', $tarefa);
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        die('Tarefa não encontrada ou fora do filtro de cidades.');
    }

    // Busca dados de execução
    $exec = null;
    $stmtExec = $pdo->prepare("
        SELECT
            tsk_id,
            MAX(e_f19181500) AS datainicio,
            MAX(e_cp_horainicial) AS horainicial,
            MAX(e_cp_datafinalizacao) AS datafinalizacao,
            MAX(e_cp_horafinalizacao) AS horafinalizacao,
            MAX(e_cp_servicoexecutado) AS servicoexecutado,
            MAX(e_cp_material) AS material_principal,
            MAX(e_cp_pavimentacao) AS pavimentacao,
            MAX(e_f19181488) AS foto_fachada,
            string_agg(DISTINCT e_cp_fotoantesexecucao1, ' ') AS fotos_antes,
            string_agg(DISTINCT e_cp_fotoaposexecucao1, ' ') AS fotos_depois,
            string_agg(DISTINCT e_cp_fotoaposexecucao2, ' ') AS fotos_depois2
        FROM u45468.dbout_history_1650496_at_execucaoservico
        WHERE tsk_id = :tarefa
        GROUP BY tsk_id
        LIMIT 1
    ");
    $stmtExec->bindValue(':tarefa', $tarefa);
    $stmtExec->execute();
    $exec = $stmtExec->fetch();

    // Fotos DURANTE a execução
    $fotosExecucaoRaw = '';
    $stmtExecFotos = $pdo->prepare("
        SELECT string_agg(DISTINCT e_f19181484, ' ') AS fotos_execucao
        FROM u45468.dbout_history_1650496_at_execucaoservico
        WHERE tsk_id = :tarefa AND acs_id = :acs_id
    ");
    $stmtExecFotos->bindValue(':tarefa', $tarefa);
    $stmtExecFotos->bindValue(':acs_id', 2605797, PDO::PARAM_INT);
    $stmtExecFotos->execute();
    $fotosExecucaoRaw = (string)($stmtExecFotos->fetchColumn() ?? '');

    [$rua, $numero] = parseEndereco($row['localizacao'] ?? '');
    $logradouro = trim($rua . ($numero !== '' ? ', ' . $numero : ''));

    // Materiais
    $itens = [];
    $ss = (string)($row['ss'] ?? '');
    if ($ss !== '') {
        $stmtItens = $pdo->prepare("
            SELECT
                Material AS material,
                Unid AS unidade,
                Quantidade AS quantidade,
                ValorTotal AS valor_total
            FROM MaterialSS
            WHERE NumeroSS = :ss
            ORDER BY ID ASC
        ");
        $stmtItens->bindValue(':ss', $ss);
        $stmtItens->execute();
        $itens = $stmtItens->fetchAll();
    }

    // Monta fotos
    $fotosAntesRaw = trim((string)($exec['fotos_antes'] ?? ''));
    $fotoFachada = trim((string)($exec['foto_fachada'] ?? ''));
    $fotosDepoisRaw = trim((string)($exec['fotos_depois'] ?? ''));
    $fotosDepois2Raw = trim((string)($exec['fotos_depois2'] ?? ''));
    $fotosApos = trim($fotosDepoisRaw . ' ' . $fotosDepois2Raw);

    $fachadaUrls = extractUrls($fotoFachada);
    $antesUrlsSemFachada = filterOutUrls($fotosAntesRaw, $fachadaUrls);

    // Gera HTML (mesmo HTML do relatorio_novo.php, mas simplificado para dompdf)
    $html = gerarHTMLRelatorio($row, $exec, $logradouro, $fachadaUrls, $antesUrlsSemFachada, $fotosExecucaoRaw, $fotosApos, $itens);

    // Configura dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Adiciona footer usando canvas de forma simples
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont("Helvetica", "normal");
    $fontSize = 6; // Fonte menor
    $pageCount = $canvas->get_page_count();
    $tarefaNum = h(dashIfEmpty($row['tarefa'] ?? ''));
    
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $canvas->page_script(function($pageNumber, $pageCount, $canvas, $fontMetrics) use ($font, $fontSize, $tarefaNum) {
            $pageWidth = $canvas->get_width();
            $pageHeight = $canvas->get_height();
            
            // Margem de 5mm em pontos
            $marginPt = 5 * 2.83465;
            
            // Posição da caixa - subiu mais
            $boxHeight = 14;
            $boxBottom = $pageHeight - $marginPt - 18; // Subiu mais a tabela
            $boxTop = $boxBottom - $boxHeight;
            $boxLeft = $marginPt;
            $boxRight = $pageWidth - $marginPt;
            $boxWidth = $boxRight - $boxLeft;
            // Divisão 70% / 30%
            $boxCenter = $boxLeft + ($boxWidth * 0.75);
            
            // Desenha bordas da tabela
            $canvas->line($boxLeft, $boxTop, $boxRight, $boxTop, [0, 0, 0], 0.5);
            $canvas->line($boxLeft, $boxBottom, $boxRight, $boxBottom, [0, 0, 0], 0.5);
            $canvas->line($boxLeft, $boxTop, $boxLeft, $boxBottom, [0, 0, 0], 0.5);
            $canvas->line($boxRight, $boxTop, $boxRight, $boxBottom, [0, 0, 0], 0.5);
            $canvas->line($boxCenter, $boxTop, $boxCenter, $boxBottom, [0, 0, 0], 0.5);
            
            // Texto centralizado verticalmente na caixa - subiu 3x mais
            $fontHeight = $fontMetrics->get_font_height($font, $fontSize);
            $textY = $boxTop + ($boxHeight / 2) - ($fontHeight * 0.5); // Subiu 3x mais o texto
            
            // Texto esquerda
            $text1 = "Nº Solicitação: $tarefaNum";
            $x1 = $boxLeft + 3;
            $canvas->text($x1, $textY, $text1, $font, $fontSize);
            
            // Texto direita - movido 72% mais para a esquerda
            $text2 = "Página: $pageNumber / $pageCount";
            $text2Width = $fontMetrics->get_text_width($text2, $font, $fontSize);
            // Calcula 72% da largura da célula direita (30% da caixa total)
            $cellRightWidth = $boxRight - $boxCenter;
            $offset72Percent = $cellRightWidth * 0.72;
            $x2 = $boxRight - $text2Width - 3 - $offset72Percent;
            $canvas->text($x2, $textY, $text2, $font, $fontSize);
            
            // Texto abaixo
            $yRodape1 = $boxBottom + 4;
            $yRodape2 = $yRodape1 + 8; // Desceu mais o "Uso interno"
            $text3 = "Documento gerado automaticamente – CESAN";
            $text4 = "Uso interno";
            $text3Width = $fontMetrics->get_text_width($text3, $font, $fontSize);
            $text4Width = $fontMetrics->get_text_width($text4, $font, $fontSize);
            $x3 = ($pageWidth - $text3Width) / 2;
            $x4 = ($pageWidth - $text4Width) / 2;
            $canvas->text($x3, $yRodape1, $text3, $font, $fontSize);
            $canvas->text($x4, $yRodape2, $text4, $font, $fontSize);
        });
    }
    
    // Nome do arquivo
    $filename = 'solicitacao_servico_' . $tarefa . '_' . date('Y-m-d_His') . '.pdf';
    
    // Verifica se deve fazer download ou apenas abrir
    $download = isset($_GET['download']) && $_GET['download'] == '1';
    
    // Envia o PDF (download se veio do botão, visualização se acesso direto)
    $dompdf->stream($filename, ['Attachment' => $download]);

} catch (Exception $e) {
    http_response_code(500);
    die('Erro ao gerar PDF: ' . h($e->getMessage()));
}

function gerarHTMLRelatorio($row, $exec, $logradouro, $fachadaUrls, $antesUrlsSemFachada, $fotosExecucaoRaw, $fotosApos, $itens) {
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page {
    size: A4;
    margin: 5mm 5mm;
}
body {
    margin: 0;
    padding-bottom: 20mm;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    color: #000;
    position: relative;
}
.page-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    font-size: 6px;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
    padding: 0;
    margin: 0;
    z-index: 1000;
    display: block;
}
.page-footer-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    border: 1px solid #000;
}
.page-footer-table td {
    border: 1px solid #000;
    padding: 2px 4px;
    font-size: 6px;
    font-weight: normal;
    vertical-align: middle;
    height: 8px;
}
.page-footer-table td:first-child {
    width: 50%;
    text-align: left;
}
.page-footer-table td:last-child {
    width: 50%;
    text-align: right;
}
.footer-text {
    font-size: 6px;
    margin-top: 3px;
    text-align: center;
    font-weight: normal;
}
table {
    width: 100%;
    border-collapse: collapse;
    page-break-inside: avoid;
}
td {
    border: 1px solid #000;
    padding: 4px;
    vertical-align: top;
}
.no-border td {
    border: none;
}
.title {
    text-align: center;
    font-weight: bold;
    font-size: 12px;
}
.subtitle {
    text-align: center;
    font-size: 10px;
    font-weight: bold;
}
.label {
    font-weight: bold;
    font-size: 9px;
}
.section {
    font-weight: bold;
    background: #eee;
}
.photos {
    min-height: 80px;
    page-break-inside: avoid;
}
img {
    max-width: 70.6%;
    height: auto;
    display: block;
    margin: 10px auto;
    border: 1px solid #000;
    page-break-inside: avoid;
    page-break-after: avoid;
    clear: both;
    float: none;
    text-align: center;
}
/* Evita que imagens sejam cortadas */
.foto-img {
    max-width: 75.6%;
    page-break-inside: avoid;
    page-break-after: avoid;
    break-inside: avoid;
    display: block;
    clear: both;
    float: none;
    margin-left: auto;
    margin-right: auto;
    text-align: center;
}
/* Fotos verticais: metade da altura e centralizadas */
.foto-img.portrait {
    max-width: 45%;
    width: 45%;
    height: auto;
    display: block;
    margin: 10px auto;
    text-align: center;
    clear: both;
    float: none;
    margin-left: auto;
    margin-right: auto;
}
/* Garante que imagens sempre fiquem uma embaixo da outra */
.block-photos {
    page-break-inside: avoid;
    display: block;
    text-align: center;
}
.block-photos img {
    display: block;
    clear: both;
    float: none;
    width: auto;
    margin-left: auto;
    margin-right: auto;
    text-align: center;
}
/* Evita que tabelas sejam cortadas */
table {
    page-break-inside: avoid;
    break-inside: avoid;
}
/* Mantém blocos juntos */
.block-photos {
    page-break-inside: avoid;
    break-inside: avoid;
}
.footer {
    font-size: 9px;
    margin-top: 6px;
    text-align: center;
}
</style>
</head>
<body>';

    // Cabeçalho
    $html .= '<table>
<tr>
<td class="no-border" style="width:20%; text-align:center; border:none">
<img src="https://whitelabel.umov.me/consglobalmetropole/CENTER_LOGO?1760720550756" style="max-width:100%; max-height:50px; border:none;">
</td>
<td class="no-border" style="width:60%; border:none; text-align:center;">
<div class="title">SOLICITAÇÃO DE SERVIÇO</div>
<div class="subtitle">CESAN – OPERACIONAL</div>
</td>
<td class="no-border" style="width:20%; border:none"></td>
</tr>
</table>';

    // Dados Gerais
    $html .= '<table>
<tr>
<td colspan="2" class="section"><span class="label">SS Nº</span> <strong>' . h(dashIfEmpty($row['ss'] ?? '')) . '</strong></td>
</tr>
<tr>
<td colspan="2">
<span class="label">Dt. Registro:</span> ' . h(dashIfEmpty($row['dt_registro'] ?? '')) . ' &nbsp;&nbsp;
<span class="label">Dt. Recepção:</span> ' . h(dashIfEmpty($row['dt_recepcao'] ?? '')) . '
</td>
</tr>
<tr>
<td><span class="label">Serviço:</span> ' . h(dashIfEmpty($row['servico'] ?? '')) . '</td>
<td><span class="label">Unidade:</span> ' . h(dashIfEmpty($row['setor'] ?? '')) . '</td>
</tr>
<tr>
<td colspan="2"><span class="label">Cliente:</span> —</td>
</tr>
<tr>
<td><span class="label">Telefone:</span> —</td>
<td><span class="label">Matrícula:</span> —</td>
</tr>
<tr>
<td colspan="2"><span class="label">Logradouro:</span> ' . h(dashIfEmpty($logradouro)) . '</td>
</tr>
<tr>
<td><span class="label">Bairro:</span> ' . h(dashIfEmpty($row['bairro'] ?? '')) . '</td>
<td><span class="label">Cidade:</span> ' . h(dashIfEmpty($row['cidade'] ?? '')) . '</td>
</tr>
<tr>
<td colspan="2"><span class="label">Referência:</span> ' . nl2br_safe($row['ref_localizacao'] ?? '') . '</td>
</tr>
<tr>
<td colspan="2"><span class="label">Inscrição / Hidrômetro:</span> —</td>
</tr>
</table>';

    // Instruções
    $html .= '<table>
<tr>
<td class="section">Instruções de Execução</td>
</tr>
<tr>
<td style="height:40px;">
' . nl2br_safe($row['informacao_solicitante'] ?? '') . '
</td>
</tr>
</table>';

    // Foto da Fachada
    if (count($fachadaUrls) > 0) {
        $html .= '<table>
<tr><td class="section">Foto da Fachada</td></tr>
<tr><td class="photos">' . renderFotosFromUrls($fachadaUrls) . '</td></tr>
</table>';
    }

    // Execução
    $html .= '<table>
<tr>
<td colspan="2" class="section">Execução do Serviço</td>
</tr>
<tr>
<td colspan="2">
<span class="label">Responsável:</span><br>
' . h(dashIfEmpty($row['quem'] ?? '')) . '
</td>
</tr>
<tr>
<td>
<span class="label">Data / Hora Início:</span><br>
' . h(formatDateTimeBR($exec['datainicio'] ?? '', $exec['horainicial'] ?? '')) . '
</td>
<td>
<span class="label">Data / Hora Fim:</span><br>
' . h(formatDateTimeBR($exec['datafinalizacao'] ?? '', $exec['horafinalizacao'] ?? '')) . '
</td>
</tr>
<tr>
<td colspan="2" style="height:40px;">
<span class="label">Descrição do Serviço Executado:</span><br>
' . nl2br_safe(($exec && isset($exec['servicoexecutado'])) ? $exec['servicoexecutado'] : ($row['esclarecimento_solicitante'] ?? '')) . '
</td>
</tr>
<tr>
<td colspan="2" style="height:40px;">
<span class="label">Retorno:</span><br>
—
</td>
</tr>
</table>';

    // Fotos Antes
    if (count($antesUrlsSemFachada) > 0) {
        $html .= '<table>
<tr><td class="section">Fotos Antes da Execução</td></tr>
<tr><td class="photos">' . renderFotosFromUrls($antesUrlsSemFachada) . '</td></tr>
</table>';
    }

    // Fotos Durante - divididas em páginas (1-2 fotos por página)
    $fotosExecucaoUrls = extractUrls($fotosExecucaoRaw);
    if (count($fotosExecucaoUrls) > 0) {
        $html .= renderFotosExecucaoPaginas($fotosExecucaoUrls);
    }

    // Fotos Após
    $fotosAposUrls = extractUrls($fotosApos);
    if (count($fotosAposUrls) > 0) {
        $html .= '<table>
<tr><td class="section">Fotos Após a Execução</td></tr>
<tr><td class="photos">' . renderFotosFromUrls($fotosAposUrls) . '</td></tr>
</table>';
    }

    // Materiais
    $html .= '<table>
<tr><td class="section">Materiais Utilizados</td></tr>
<tr><td style="height:60px;">' . renderMateriaisBoxHtml($exec['material_principal'] ?? '', $exec['pavimentacao'] ?? '', $itens) . '</td></tr>
</table>';

    // Footer removido - agora é adicionado apenas via canvas
    
    $html .= '</body></html>';
    return $html;
}

