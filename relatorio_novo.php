<?php
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

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
    // já está dd/mm/yyyy
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) return $s;
    // tenta yyyy-mm-dd (com ou sem horário)
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
    // normaliza HH:MM[:SS] -> HH:MM
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

function renderFotosHtml($raw) {
    $s = trim((string)($raw ?? ''));
    if ($s === '') return '<div style="color:#444;">—</div>';

    $urls = extractUrls($s);
    return renderFotosFromUrls($urls);
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

function renderFotosFromUrls($urls, $imgClass = '') {
    if (!is_array($urls) || count($urls) === 0) return '<div style="color:#444;">—</div>';
    $imgs = '';
    $cls = trim((string)$imgClass);
    // Sempre adiciona 'foto-img' para detecção de orientação vertical
    $clsFinal = 'foto-img' . ($cls !== '' ? ' ' . $cls : '');
    $clsAttr = ' class="' . h($clsFinal) . '"';
    foreach ($urls as $u) {
        $imgs .= '<img src="' . h($u) . '" alt="foto"' . $clsAttr . '>';
    }
    return '<div class="img-grid">' . $imgs . '</div>';
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

$tarefa = isset($_GET['tarefa']) ? trim($_GET['tarefa']) : '';
if ($tarefa === '') {
    http_response_code(400);
    echo '<h3 style="font-family:Arial;">Parâmetro inválido: tarefa</h3>';
    exit;
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
        INNER JOIN u45468.dbout_tmp_local2 AS l ON l.loc_id = t.loc_id
        LEFT JOIN u45468.dbout_agent AS a ON t.age_id = a.age_id
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
        echo '<h3 style="font-family:Arial;">Tarefa não encontrada ou fora do filtro de cidades.</h3>';
        exit;
    }

    // Busca dados de execução do serviço (histórico)
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

    // Fotos DURANTE a execução (acs_id específico)
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

    // Materiais (se existirem)
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

    // Monta fotos (fachada separada + antes / durante / depois)
    $fotosAntesRaw = trim((string)($exec['fotos_antes'] ?? ''));
    $fotoFachada = trim((string)($exec['foto_fachada'] ?? ''));

    $fotosDepoisRaw = trim((string)($exec['fotos_depois'] ?? ''));
    $fotosDepois2Raw = trim((string)($exec['fotos_depois2'] ?? ''));
    $fotosApos = trim($fotosDepoisRaw . ' ' . $fotosDepois2Raw);

    // Remove fachada de "antes" se vier repetida
    $fachadaUrls = extractUrls($fotoFachada);
    $antesUrlsSemFachada = filterOutUrls($fotosAntesRaw, $fachadaUrls);

    $map = [
        '{{SS_NUMERO}}' => h(dashIfEmpty($row['ss'] ?? '')),
        '{{DT_REGISTRO}}' => h(dashIfEmpty($row['dt_registro'] ?? '')),
        '{{DT_RECEPCAO}}' => h(dashIfEmpty($row['dt_recepcao'] ?? '')),
        '{{SERVICO}}' => h(dashIfEmpty($row['servico'] ?? '')),
        '{{UNIDADE}}' => h(dashIfEmpty($row['setor'] ?? '')),
        '{{CLIENTE}}' => '—',
        '{{TELEFONE}}' => '—',
        '{{MATRICULA}}' => '—',
        '{{LOGRADOURO}}' => h(dashIfEmpty($logradouro)),
        '{{BAIRRO}}' => h(dashIfEmpty($row['bairro'] ?? '')),
        '{{CIDADE}}' => h(dashIfEmpty($row['cidade'] ?? '')),
        '{{REFERENCIA}}' => nl2br_safe($row['ref_localizacao'] ?? ''),
        '{{HIDROMETRO}}' => '—',
        '{{INSTRUCAO_EXECUCAO}}' => nl2br_safe($row['informacao_solicitante'] ?? ''),
        '{{RESPONSAVEL}}' => h(dashIfEmpty($row['quem'] ?? '')),
        '{{EQUIPE}}' => '—',
        '{{DATA_INICIO}}' => h(formatDateTimeBR($exec['datainicio'] ?? '', $exec['horainicial'] ?? '')),
        '{{DATA_FIM}}' => h(formatDateTimeBR($exec['datafinalizacao'] ?? '', $exec['horafinalizacao'] ?? '')),
        '{{DESCRICAO_EXECUCAO}}' => nl2br_safe(($exec && isset($exec['servicoexecutado'])) ? $exec['servicoexecutado'] : ($row['esclarecimento_solicitante'] ?? '')),
        '{{RETORNO}}' => '—',
        '{{FOTO_FACHADA}}' => renderFotosFromUrls($fachadaUrls, 'fachada-img'),
        '{{FOTOS_ANTES}}' => renderFotosFromUrls($antesUrlsSemFachada),
        '{{FOTOS_EXECUCAO}}' => renderFotosHtml($fotosExecucaoRaw),
        '{{FOTOS_APOS}}' => renderFotosHtml($fotosApos),
        '{{MATERIAIS}}' => renderMateriaisBoxHtml($exec['material_principal'] ?? '', $exec['pavimentacao'] ?? '', $itens),
        '{{NUM_SOLICITACAO}}' => h(dashIfEmpty($row['tarefa'] ?? '')),
        '{{PAGINA}}' => ''
    ];

    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Solicitação de Serviço</title>

<style>
@page {
    size: A4;
    margin: 0;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    color: #000;
}

/* Barra de ações (não imprime) */
.topbar {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    background: #fafafa;
    position: sticky;
    top: 0;
    z-index: 9999;
}
.topbar button {
    background: #1976d2;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    font-weight: 600;
    cursor: pointer;
}
.topbar button:hover { background: #1256a3; }

@media print {
    .topbar { display: none; }
}

.page {
    width: 210mm;
    height: 297mm;
    border: 2px solid #000;
    padding: 5mm;
    box-sizing: border-box;
    page-break-after: always;
}
.page:last-child { page-break-after: auto; }

.page-inner{
    height: 100%;
    display: flex;
    flex-direction: column;
}
.page-content{
    flex: 1;
    overflow: hidden;
}
.page-footer{
    flex: 0 0 auto;
}
.spacer { height: 6mm; }

table {
    width: 100%;
    border-collapse: collapse;
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
}

.img-grid{
    display: flex;
    flex-direction: column; /* uma foto por linha */
    gap: 10px;
}
.img-grid img{
    width: 75%;            /* um pouco menor e centralizado */
    max-width: 75%;
    height: auto;
    object-fit: contain;
    border: 1px solid #000;
    display: block;
    margin: 0 auto;
}

/* Qualquer foto vertical: diminui pela metade (ajustado via JS com classe .portrait) */
.img-grid img.foto-img.portrait{
    width: 45%;
    max-width: 45%;
}

.footer {
    font-size: 9px;
    margin-top: 6px;
    text-align: center;
}
</style>
</head>

<body>

<div class="topbar">
    <button type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
</div>

<div id="pages"></div>

<!-- conteúdo “fonte” (JS faz a paginação) -->
<div id="content" style="display:none;">

<!-- ================= CABEÇALHO ================= -->
<div class="block">
<table>
<tr>
<td class="no-border" style="width:20%; text-align:center; border:none">
<img src="https://whitelabel.umov.me/consglobalmetropole/CENTER_LOGO?1760720550756" style="max-width:100%; max-height:50px">
</td>

<td class="no-border" style="width:60%; border:none; text-align:center;">
<div class="title">SOLICITAÇÃO DE SERVIÇO</div>
<div class="subtitle">CESAN – OPERACIONAL</div>
</td>

<td class="no-border" style="width:20%; border:none"></td>
</tr>
</table>
</div>

<div class="spacer"></div>

<!-- ================= DADOS GERAIS ================= -->
<div class="block">
<table>
<tr>
<td colspan="2" class="section"><span class="label">SS Nº</span> <strong>{{SS_NUMERO}}</strong></td>
</tr>
<tr>
<td colspan="2">
<span class="label">Dt. Registro:</span> {{DT_REGISTRO}} &nbsp;&nbsp;
<span class="label">Dt. Recepção:</span> {{DT_RECEPCAO}}
</td>
</tr>

<tr>
<td><span class="label">Serviço:</span> {{SERVICO}}</td>
<td><span class="label">Unidade:</span> {{UNIDADE}}</td>
</tr>

<tr>
<td colspan="2"><span class="label">Cliente:</span> {{CLIENTE}}</td>
</tr>

<tr>
<td><span class="label">Telefone:</span> {{TELEFONE}}</td>
<td><span class="label">Matrícula:</span> {{MATRICULA}}</td>
</tr>

<tr>
<td colspan="2"><span class="label">Logradouro:</span> {{LOGRADOURO}}</td>
</tr>

<tr>
<td><span class="label">Bairro:</span> {{BAIRRO}}</td>
<td><span class="label">Cidade:</span> {{CIDADE}}</td>
</tr>

<tr>
<td colspan="2"><span class="label">Referência:</span> {{REFERENCIA}}</td>
</tr>

<tr>
<td colspan="2"><span class="label">Inscrição / Hidrômetro:</span> {{HIDROMETRO}}</td>
</tr>
</table>
</div>

<div class="spacer"></div>

<!-- ================= INSTRUÇÕES ================= -->
<div class="block">
<table>
<tr>
<td class="section">Instruções de Execução</td>
</tr>
<tr>
<td style="height:40px;">
{{INSTRUCAO_EXECUCAO}}
</td>
</tr>
</table>
</div>

<div class="spacer"></div>

<!-- ================= FOTO DA FACHADA (SEMPRE NA 1ª PÁGINA) ================= -->
<div class="block block-fachada">
<table>
<tr><td class="section">Foto da Fachada</td></tr>
<tr><td class="photos">{{FOTO_FACHADA}}</td></tr>
</table>
</div>

<div class="spacer"></div>

<!-- ================= EXECUÇÃO ================= -->
<div class="block">
<table>
<tr>
<td colspan="2" class="section">Execução do Serviço</td>
</tr>

<tr>
<td colspan="2">
<span class="label">Responsável:</span><br>
{{RESPONSAVEL}}
</td>
</tr>

<tr>
<td>
<span class="label">Data / Hora Início:</span><br>
{{DATA_INICIO}}
</td>
<td>
<span class="label">Data / Hora Fim:</span><br>
{{DATA_FIM}}
</td>
</tr>

<tr>
<td colspan="2" style="height:40px;">
<span class="label">Descrição do Serviço Executado:</span><br>
{{DESCRICAO_EXECUCAO}}
</td>
</tr>

<tr>
<td colspan="2" style="height:40px;">
<span class="label">Retorno:</span><br>
{{RETORNO}}
</td>
</tr>
</table>
</div>

<div class="spacer"></div>

<!-- ================= FOTOS ================= -->
<div class="block">
<table>
<tr><td class="section">Fotos Antes da Execução</td></tr>
<tr><td class="photos">{{FOTOS_ANTES}}</td></tr>
</table>
</div>

<div class="spacer"></div>

<div class="block block-fotos-execucao">
<table>
<tr><td class="section">Fotos Durante a Execução</td></tr>
<tr><td class="photos">{{FOTOS_EXECUCAO}}</td></tr>
</table>
</div>

<div class="spacer"></div>

<div class="block block-fotos-apos">
<table>
<tr><td class="section">Fotos Após a Execução</td></tr>
<tr><td class="photos">{{FOTOS_APOS}}</td></tr>
</table>
</div>

<div class="spacer"></div>

<!-- ================= MATERIAIS ================= -->
<div class="block">
<table>
<tr><td class="section">Materiais Utilizados</td></tr>
<tr><td style="height:60px;">{{MATERIAIS}}</td></tr>
</table>
</div>

</div>

<script>
(function() {
    function createPage() {
        const page = document.createElement('div');
        page.className = 'page';
        page.innerHTML = `
            <div class="page-inner">
                <div class="page-content"></div>
                <div class="page-footer">
                    <div class="spacer"></div>
                    <table>
                        <tr>
                            <td><span class="label">Nº Solicitação:</span> {{NUM_SOLICITACAO}}</td>
                            <td><span class="label">Página:</span> <span class="page-num"></span>/<span class="page-total"></span></td>
                        </tr>
                    </table>
                    <div class="footer">
                        Documento gerado automaticamente – CESAN<br>
                        Uso interno
                    </div>
                </div>
            </div>
        `;
        return {
            page,
            content: page.querySelector('.page-content')
        };
    }

    function waitImages(root) {
        const imgs = Array.from(root.querySelectorAll('img'));
        return Promise.all(imgs.map(img => {
            if (img.complete) return Promise.resolve();
            return new Promise(resolve => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            });
        }));
    }

    function markFachadaOrientation(root) {
        // Detecta orientação vertical em TODAS as fotos (não só fachada)
        const imgs = Array.from(root.querySelectorAll('img.foto-img'));
        imgs.forEach(img => {
            try {
                const w = img.naturalWidth || 0;
                const h = img.naturalHeight || 0;
                if (w > 0 && h > 0 && h > w) img.classList.add('portrait');
                else img.classList.remove('portrait');
            } catch (e) {}
        });
    }

    function paginate() {
        const contentRoot = document.getElementById('content');
        const pagesRoot = document.getElementById('pages');
        if (!contentRoot || !pagesRoot) return;

        pagesRoot.innerHTML = '';

        const blocks = Array.from(contentRoot.querySelectorAll('.block'));
        let current = createPage();
        pagesRoot.appendChild(current.page);
        
        // Separa blocos especiais
        const fachadaBlock = blocks.find(b => b.classList.contains('block-fachada'));
        const fotosExecucaoBlock = blocks.find(b => b.classList.contains('block-fotos-execucao'));
        const fotosAposBlock = blocks.find(b => b.classList.contains('block-fotos-apos'));
        const outrosBlocks = blocks.filter(b => 
            !b.classList.contains('block-fachada') && 
            !b.classList.contains('block-fotos-execucao') &&
            !b.classList.contains('block-fotos-apos')
        );
        
        // Processa blocos na ordem: outros até Instruções, depois Fachada, depois Execução, depois Fotos Execução, depois Fotos Após, depois resto
        let jaAdicionouFachada = false;
        
        outrosBlocks.forEach(block => {
            const texto = block.textContent || '';
            const isInstrucoes = texto.includes('Instruções de Execução');
            const isExecucao = texto.includes('Execução do Serviço');
            const isFotosAntes = texto.includes('Fotos Antes');
            const isMateriais = texto.includes('Materiais Utilizados');
            
            // Pula blocos que serão processados depois
            if (isMateriais) {
                return; // Será processado depois
            }
            
            const clone = block.cloneNode(true);
            current.content.appendChild(clone);
            void clone.offsetHeight;
            
            if (isInstrucoes && fachadaBlock && !jaAdicionouFachada) {
                const fachadaClone = fachadaBlock.cloneNode(true);
                current.content.appendChild(fachadaClone);
                void fachadaClone.offsetHeight;
                jaAdicionouFachada = true;
                
                // Verifica se ultrapassou após adicionar fachada
                if (current.content.scrollHeight > current.content.clientHeight) {
                    // Se ultrapassou, fachada vai para próxima página (mas deve tentar manter na primeira)
                    // Por enquanto, deixa na primeira mesmo se ultrapassar um pouco
                }
            }
            
            // Verifica se ultrapassou após adicionar
            if (current.content.scrollHeight > current.content.clientHeight) {
                current.content.removeChild(clone);
                current = createPage();
                pagesRoot.appendChild(current.page);
                current.content.appendChild(clone);
            }
        });
        
        // Processa Fotos Durante a Execução: 2 fotos por página
        if (fotosExecucaoBlock) {
            const cloneOriginal = fotosExecucaoBlock.cloneNode(true);
            const fotos = Array.from(cloneOriginal.querySelectorAll('img.foto-img'));
            const totalFotos = fotos.length;
            
            if (totalFotos > 0) {
                // Divide em grupos de 2 fotos
                for (let i = 0; i < totalFotos; i += 2) {
                    const grupoFotos = fotos.slice(i, i + 2);
                    
                    // Cria um novo bloco com apenas essas fotos
                    const novoBloco = document.createElement('div');
                    novoBloco.className = 'block';
                    novoBloco.innerHTML = `
                        <table>
                            <tr><td class="section">Fotos Durante a Execução</td></tr>
                            <tr><td class="photos">
                                <div class="img-grid">
                                    ${grupoFotos.map(img => img.outerHTML).join('')}
                                </div>
                            </td></tr>
                        </table>
                    `;
                    
                    const clone = novoBloco.cloneNode(true);
                    current.content.appendChild(clone);
                    void clone.offsetHeight;
                    
                    // Verifica se ultrapassou
                    if (current.content.scrollHeight > current.content.clientHeight) {
                        current.content.removeChild(clone);
                        current = createPage();
                        pagesRoot.appendChild(current.page);
                        current.content.appendChild(clone);
                        void clone.offsetHeight;
                    }
                    
                    // Se não é o último grupo, cria nova página para o próximo
                    if (i + 2 < totalFotos) {
                        current = createPage();
                        pagesRoot.appendChild(current.page);
                    }
                }
            }
        }
        
        // Processa Fotos Após a Execução: DEPOIS de todas as páginas de "Durante"
        if (fotosAposBlock) {
            const clone = fotosAposBlock.cloneNode(true);
            current.content.appendChild(clone);
            void clone.offsetHeight;
            
            // Verifica se ultrapassou
            if (current.content.scrollHeight > current.content.clientHeight) {
                current.content.removeChild(clone);
                current = createPage();
                pagesRoot.appendChild(current.page);
                current.content.appendChild(clone);
                void clone.offsetHeight;
            }
        }
        
        // Processa os blocos restantes (Materiais, etc) - que foram pulados no loop anterior
        outrosBlocks.forEach(block => {
            const texto = block.textContent || '';
            const isMateriais = texto.includes('Materiais Utilizados');
            
            // Processa apenas Materiais e outros que não foram processados
            if (isMateriais) {
                const clone = block.cloneNode(true);
                current.content.appendChild(clone);
                void clone.offsetHeight;
                
                // Verifica se ultrapassou após adicionar
                if (current.content.scrollHeight > current.content.clientHeight) {
                    current.content.removeChild(clone);
                    current = createPage();
                    pagesRoot.appendChild(current.page);
                    current.content.appendChild(clone);
                }
            }
        });

        // Ajusta orientação/tamanho das fotos também nas páginas montadas
        markFachadaOrientation(pagesRoot);

        const pages = Array.from(pagesRoot.querySelectorAll('.page'));
        const total = pages.length || 1;
        pages.forEach((p, idx) => {
            const n = p.querySelector('.page-num');
            const t = p.querySelector('.page-total');
            if (n) n.textContent = String(idx + 1);
            if (t) t.textContent = String(total);
        });
    }

    async function init() {
        const contentRoot = document.getElementById('content');
        if (!contentRoot) return;
        await waitImages(contentRoot);
        markFachadaOrientation(contentRoot);
        paginate();
    }

    window.addEventListener('load', init);
    window.addEventListener('beforeprint', paginate);
})();
</script>

</body>
</html>
HTML;

    $html = strtr($html, $map);
    echo $html;
} catch (Exception $e) {
    http_response_code(500);
    echo '<h3 style="font-family:Arial;">Erro ao gerar relatório: ' . h($e->getMessage()) . '</h3>';
}


