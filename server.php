<?php
header('Content-Type: application/json; charset=utf-8');

// Inclui o arquivo de configuração
require_once 'config.php';

// Parâmetros do DataTables
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

// Mapeamento de nomes do DataTables para nomes reais do banco
$colMap = [
    'quem' => 'a.age_name',
    'ss' => 'l.loc_integrationid',
    'ss_numero' => 'l.loc_integrationid',
    'rua' => 'l.loc_description',
    'numero' => 'l.loc_description',
    'localizacao' => 'l.loc_description',
    'cidade' => 'l.e_localidade',
    'bairro' => 'l.e_bairro',
    'setor' => 'l.e_setor',
    'recepcionado' => 'l.e_dataregistro',
    'ultima_atividade' => 't.tsk_lastexecutiondatehour',
    'situacao' => 't.tsk_situation',
    'tarefa' => 't.tsk_id',
    'prioridade' => 't.tsk_priority',
    'servico' => 'tt.tty_description',
    'tags' => 'l.e_tag',
    'status_integracao' => 'l.e_situacao'
];

// Colunas que precisam de CAST para texto (usando os nomes do colMap)
$castCols = ['t.tsk_id', 't.tsk_priority', 'l.loc_integrationid'];

// Proteção: só permite ordenar por colunas conhecidas
$allowedCols = array_keys($colMap);

// Ordenação dinâmica
$orderBy = '';
if (!empty($_POST['order']) && isset($_POST['columns'])) {
    $orderColIdx = intval($_POST['order'][0]['column']);
    $orderDir = $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
    $columns = $_POST['columns'];
    $colName = $columns[$orderColIdx]['data'];
    if (in_array($colName, $allowedCols)) {
        $dbCol = $colMap[$colName];
        // Para campos com prefixo de tabela, não usar aspas
        if (strpos($dbCol, '.') !== false) {
            $orderBy = "ORDER BY $dbCol $orderDir";
        } else {
            $orderBy = "ORDER BY \"$dbCol\" $orderDir";
        }
    }
}
if($orderBy == ''){
    $orderBy = 'ORDER BY t.tsk_datetimeinsert DESC';
}

// Filtros por coluna
$where = [];
$params = [];

// Filtro de aba (tab)
$activeTab = isset($_POST['activeTab']) ? $_POST['activeTab'] : '';
if ($activeTab) {
    switch ($activeTab) {
        case 'recepcao':
            // RECEPÇÃO: Pendente de envio pra campo
            $where[] = "t.tsk_situation = 'PENDENTE DE ENVIO PARA CAMPO'";
            break;
        case 'com-equipes':
            // COM EQUIPES: Notas em campo
            $where[] = "t.tsk_situation = 'EM CAMPO'";
            break;
        case 'notas-para-baixar':
            // NOTAS PARA BAIXAR: situação = "RETORNADA DE CAMPO" E status_integracao vazio ou NULL
            $where[] = "t.tsk_situation = 'RETORNADA DE CAMPO'";
            $where[] = "(l.e_situacao IS NULL OR l.e_situacao = '')";
            break;
        case 'notas-baixadas':
            // NOTAS BAIXADAS: status_integracao preenchido (não vazio)
            $where[] = "(l.e_situacao IS NOT NULL AND l.e_situacao != '')";
            break;
    }
}

// Inicializa o filtro de cidade da CTE
$cidadesAtivas = getCidadesAtivas();
// Inicializa o filtro de cidade da CTE
$cidadeWhereCTE = getCidadeWhereCondition('u45468.dbout_tmp_local2');

// Filtro fixo para situação baseado na configuração - REMOVIDO para permitir filtro livre no frontend
// $situacaoWhere = getSituacaoWhereCondition('t');
// if ($situacaoWhere) {
//     $where[] = $situacaoWhere;
// }

// Filtro fixo para cidade baseado na configuração - REMOVIDO pois já é aplicado na CTE
// $cidadeWhere = getCidadeWhereCondition();
// if ($cidadeWhere) {
//     $where[] = $cidadeWhere;
// }

if (!empty($_POST['columns'])) {
    foreach ($_POST['columns'] as $col) {
        $colName = $col['data'];
        $searchVal = trim($col['search']['value'] ?? '');
        // Ignora o filtro de situação se a aba "notas-para-baixar" estiver ativa (já filtra por situação)
        if ($colName == 'situacao' && $activeTab == 'notas-para-baixar') {
            continue;
        }
        // Ignora o filtro de situação se as abas "recepcao" ou "com-equipes" estiverem ativas (já filtram por situação)
        if ($colName == 'situacao' && ($activeTab == 'recepcao' || $activeTab == 'com-equipes')) {
            continue;
        }
        // Só filtra se o valor não for vazio e for uma coluna permitida
        if ($searchVal !== '' && in_array($colName, $allowedCols)) {
            $dbCol = $colMap[$colName];
            if ($colName == 'recepcionado' || $colName == 'ultima_atividade') {
                $where[] = "TO_CHAR($dbCol, 'DD/MM/YYYY') = :$colName";
                $params[$colName] = $searchVal;
            } else if ($colName == 'servico' && strpos($searchVal, ',') !== false) {
                // Filtro múltiplo de serviços
                $servicos = array_map('trim', explode(',', $searchVal));
                $inParams = [];
                foreach ($servicos as $idx => $srv) {
                    $paramKey = ":{$colName}_$idx";
                    $inParams[] = $paramKey;
                    $params[$colName . "_$idx"] = $srv;
                }
                $where[] = "$dbCol IN (" . implode(",", $inParams) . ")";
            } else if ($colName == 'cidade') {
                // Filtro de cidade - modifica a CTE ao invés do WHERE
                $cidadeFiltrada = $searchVal;
                // Verifica se a cidade filtrada está entre as disponíveis no config
                if (in_array($cidadeFiltrada, $cidadesAtivas)) {
                    $cidadeWhereCTE = "u45468.dbout_tmp_local2.e_localidade = '$cidadeFiltrada'";
                }
                // Se não estiver nas cidades configuradas, mantém o filtro original
            } else if ($colName == 'rua' || $colName == 'numero') {
                // Filtro de rua ou número - busca no campo localizacao
                // Para rua, busca no texto após "END:"
                // Para número, busca números após vírgula no campo localizacao
                if ($colName == 'rua') {
                    $where[] = "l.loc_description ILIKE :$colName";
                    $params[$colName] = "%$searchVal%";
                } else {
                    // Para número, busca padrão ", NUMERO" ou " NUMERO" no campo localizacao usando ILIKE
                    $where[] = "(l.loc_description ILIKE :{$colName}_1 OR l.loc_description ILIKE :{$colName}_2)";
                    $params[$colName . '_1'] = "%, $searchVal%";
                    $params[$colName . '_2'] = "% $searchVal%";
                }
            } else if (in_array($dbCol, $castCols)) {
                $where[] = "CAST($dbCol AS TEXT) ILIKE :$colName";
                $params[$colName] = "%$searchVal%";
            } else {
                $where[] = "$dbCol ILIKE :$colName";
                $params[$colName] = "%$searchVal%";
            }
        }
    }
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Endpoint para buscar agentes
if (isset($_GET['agentes'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $sql = "SELECT age_name, age_id, age_login FROM u45468.dbout_agent WHERE age_login LIKE 'equipe%' and age_active = '1' ORDER BY age_login";
        $stmt = $pdo->query($sql);
        $agentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($agentes, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Endpoint para buscar cidades
if (isset($_GET['cidades'])) {
    try {
        // Retorna apenas as cidades configuradas no filtro
        $cidadesAtivas = getCidadesAtivas();
        $cidades = [];
        foreach ($cidadesAtivas as $cidade) {
            $cidades[] = ['cidade' => $cidade];
        }
        
        echo json_encode($cidades, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Endpoint para buscar situações
if (isset($_GET['situacoes'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Busca todas as situações distintas disponíveis no banco
        $sql = "SELECT DISTINCT tsk_situation as situacao 
                FROM u45468.task 
                WHERE tsk_situation IS NOT NULL 
                AND tsk_situation != '' 
                ORDER BY tsk_situation ASC";
        
        $stmt = $pdo->query($sql);
        $situacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($situacoes, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Endpoint para buscar materiais
if (isset($_GET['materiais'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $sql = "SELECT 
                    cev_id as id,
                    cev_description as nome,
                    COALESCE(i_unidade, e_unidade, 'UN') as unidade,
                    i_valorunit as valor_unitario
                FROM u45468.dbout_customentity_mc_cadastroni 
                WHERE cev_active = '1' AND i_visivel = '1' 
                ORDER BY cev_description ASC";
        $stmt = $pdo->query($sql);
        $materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($materiais, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}


// Endpoint para buscar itens de uma tarefa
if (isset($_GET['itens']) && isset($_GET['ss'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $ss = $_GET['ss'];
        
        // Busca os itens da tabela MaterialSS
        $sql = "SELECT 
                    ID as id,
                    Material as material,
                    Unid as unidade,
                    Quantidade as quantidade,
                    ValorTotal as valor_total
                FROM MaterialSS 
                WHERE NumeroSS = :ss
                ORDER BY ID ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':ss', $ss);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($itens, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Endpoint para inserir item na tabela MaterialSS
if (isset($_POST['inserir_item'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $ss = $_POST['ss'];
        $material = $_POST['material'];
        $unidade = $_POST['unidade'];
        $quantidade = $_POST['quantidade'];
        $valorTotal = $_POST['valor_total'];
        
        // Busca o nome do material
        $sqlMaterial = "SELECT cev_description FROM u45468.dbout_customentity_mc_cadastroni WHERE cev_id = :material_id";
        $stmtMaterial = $pdo->prepare($sqlMaterial);
        $stmtMaterial->bindValue(':material_id', $material);
        $stmtMaterial->execute();
        $nomeMaterial = $stmtMaterial->fetchColumn();
        
        if (!$nomeMaterial) {
            echo json_encode(['success' => false, 'message' => 'Material não encontrado']);
            exit;
        }
        
        // Insere o item na tabela MaterialSS
        $sql = "INSERT INTO MaterialSS (NumeroSS, Material, Unid, Quantidade, ValorTotal) 
                VALUES (:ss, :material, :unidade, :quantidade, :valor_total)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':ss', $ss);
        $stmt->bindValue(':material', $nomeMaterial);
        $stmt->bindValue(':unidade', $unidade);
        $stmt->bindValue(':quantidade', $quantidade);
        $stmt->bindValue(':valor_total', $valorTotal);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Item adicionado com sucesso!']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao inserir item: ' . $e->getMessage()]);
    }
    exit;
}

// Endpoint para excluir item da tabela MaterialSS
if (isset($_POST['excluir_item'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $itemId = $_POST['item_id'];
        
        // Exclui o item da tabela MaterialSS
        $sql = "DELETE FROM MaterialSS WHERE ID = :item_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':item_id', $itemId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Item excluído com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item não encontrado']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir item: ' . $e->getMessage()]);
    }
    exit;
}

// Endpoint para atualizar status na tabela dbout_tmp_local2
if (isset($_POST['atualizar_status'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $locId = $_POST['loc_id'];
        $novoStatus = $_POST['novo_status'];
        
        // Atualiza o status na tabela dbout_tmp_local2
        $sql = "UPDATE u45468.dbout_tmp_local2 
                SET e_situacao = :novo_status 
                WHERE loc_id = :loc_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':novo_status', $novoStatus);
        $stmt->bindValue(':loc_id', $locId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Busca os dados atualizados para retornar
            $sqlSelect = "SELECT e_situacao FROM u45468.dbout_tmp_local2 WHERE loc_id = :loc_id";
            $stmtSelect = $pdo->prepare($sqlSelect);
            $stmtSelect->bindValue(':loc_id', $locId);
            $stmtSelect->execute();
            $dadosAtualizados = $stmtSelect->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Status atualizado na base de dados com sucesso!',
                'dados_atualizados' => $dadosAtualizados
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status: ' . $e->getMessage()]);
    }
    exit;
}

// Endpoint para atualizar tags na tabela dbout_tmp_local2
if (isset($_POST['atualizar_tags'])) {
    try {
        $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $locId = $_POST['loc_id'];
        $novaTag = $_POST['nova_tag'];
        
        // Busca as tags atuais
        $sqlSelect = "SELECT e_tag FROM u45468.dbout_tmp_local2 WHERE loc_id = :loc_id";
        $stmtSelect = $pdo->prepare($sqlSelect);
        $stmtSelect->bindValue(':loc_id', $locId);
        $stmtSelect->execute();
        $tagsAtuais = $stmtSelect->fetchColumn();
        
        // Concatena a nova tag com as existentes
        $tagsArray = $tagsAtuais ? explode(',', $tagsAtuais) : [];
        if (!in_array($novaTag, $tagsArray)) {
            $tagsArray[] = $novaTag;
        }
        $tagsConcatenadas = implode(',', $tagsArray);
        
        // Atualiza as tags na tabela dbout_tmp_local2
        $sql = "UPDATE u45468.dbout_tmp_local2 
                SET e_tag = :tags 
                WHERE loc_id = :loc_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tags', $tagsConcatenadas);
        $stmt->bindValue(':loc_id', $locId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Tag adicionada na base de dados com sucesso!',
                'dados_atualizados' => ['e_tag' => $tagsConcatenadas]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar tags: ' . $e->getMessage()]);
    }
    exit;
}

try {
    $pdo = new PDO(getConnectionString(), $DB_CONFIG['user'], $DB_CONFIG['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 60, // Timeout de 60 segundos
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false // Usa prepared statements nativos
    ]);
    
    // Query de contagem otimizada - usa CTE
    $totalSql = "WITH locais_filtrados AS (
        SELECT *
        FROM u45468.dbout_tmp_local2
        WHERE $cidadeWhereCTE
    )
    SELECT COUNT(*) FROM u45468.task AS t
        INNER JOIN locais_filtrados AS l ON l.loc_id = t.loc_id";
    $total = $pdo->query($totalSql)->fetchColumn();

    // Total de registros filtrados - OTIMIZADO COM CTE
    $filteredSql = "WITH locais_filtrados AS (
        SELECT *
        FROM u45468.dbout_tmp_local2
        WHERE $cidadeWhereCTE
    )
    SELECT COUNT(DISTINCT t.tsk_id) FROM u45468.task AS t
        INNER JOIN locais_filtrados AS l ON l.loc_id = t.loc_id
        LEFT JOIN u45468.dbout_agent AS a ON t.age_id = a.age_id
        INNER JOIN u45468.tasktype AS tt ON tt.tty_id = t.tty_id";
    
    // Adiciona outros filtros se existirem
    if (!empty($where)) {
        $additionalWhere = [];
        foreach ($where as $condition) {
            // Inclui todos os filtros, incluindo cidade (que será aplicado na CTE também)
            $additionalWhere[] = $condition;
        }
        if (!empty($additionalWhere)) {
            $filteredSql .= "\nWHERE " . implode(' AND ', $additionalWhere);
        }
    }
    
    // Se não houver filtros adicionais, usa o mesmo valor do total
    if (empty($where)) { // Sem filtros adicionais
        $filteredTotal = $total;
    } else {
        $filteredStmt = $pdo->prepare($filteredSql);
        foreach ($params as $key => $val) {
            $filteredStmt->bindValue(":$key", $val);
        }
        $filteredStmt->execute();
        $filteredTotal = $filteredStmt->fetchColumn();
    }

    // Query principal com paginação - OTIMIZADA COM CTE
    $sql = <<<SQL
WITH locais_filtrados AS (
    SELECT *
    FROM u45468.dbout_tmp_local2
    WHERE $cidadeWhereCTE
)
SELECT
    a.age_name AS "quem",
    l.loc_integrationid AS "ss",
    l.loc_description AS "localizacao",
    l.e_localidade AS "cidade",
    t.tss_id AS "tss_id",
    l.e_bairro AS "bairro",
    l.loc_id AS "loc_id",
    l.e_setor AS "setor",
    TO_CHAR(l.e_dataregistro::date, 'DD/MM/YYYY') AS "recepcionado",
    TO_CHAR(t.tsk_lastexecutiondatehour, 'DD/MM/YYYY') AS "ultima_atividade",
    t.tsk_situation AS "situacao",
    t.tsk_id AS "tarefa",
    tt.tty_description AS "servico",
    l.e_tag AS "tags",
    COALESCE(l.e_situacao, NULL) AS "status_integracao",
    CASE 
        WHEN t.tss_id = 50 THEN CONCAT('https://consglobalmetropole.umov.me/CenterWeb/report/schedule/', t.tsk_id, '/', t.tsk_accesstoken)
        ELSE NULL
    END AS "link",
    t.tsk_priority as "prioridade",
    COALESCE(COUNT(m.ID), 0) AS "numero_itens"
FROM u45468.task AS t
INNER JOIN locais_filtrados AS l ON l.loc_id = t.loc_id
LEFT JOIN u45468.dbout_agent AS a ON t.age_id = a.age_id
INNER JOIN u45468.tasktype AS tt ON tt.tty_id = t.tty_id
LEFT JOIN MaterialSS AS m ON m.NumeroSS = l.loc_integrationid
SQL;

    // Adiciona outros filtros se existirem
    if (!empty($where)) { // Se há filtros adicionais
        $additionalWhere = [];
        foreach ($where as $condition) {
            // Inclui todos os filtros, incluindo cidade (que será aplicado na CTE também)
            $additionalWhere[] = $condition;
        }
        if (!empty($additionalWhere)) {
            $sql .= "\nWHERE " . implode(' AND ', $additionalWhere);
        }
    }

    // Adiciona GROUP BY para o contador de itens
    $sql .= "\nGROUP BY a.age_name, l.loc_integrationid, l.loc_description, l.e_localidade, t.tss_id, l.e_bairro, l.loc_id, l.e_setor, l.e_dataregistro, t.tsk_lastexecutiondatehour, t.tsk_situation, t.tsk_id, tt.tty_description, l.e_tag, l.e_situacao, t.tsk_priority, t.tsk_accesstoken";

    // Aplica a ordenação solicitada pelo usuário
    $sql .= "\n$orderBy";
    
    $sql .= "\nLIMIT :length OFFSET :start";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Processa os dados para separar SS, Rua e Número do campo localizacao
    foreach ($result as &$row) {
        $localizacao = $row['localizacao'] ?? '';
        
        // Extrai SS (já temos em 'ss', mas vamos garantir)
        $row['ss_numero'] = $row['ss'] ?? '';
        
        // Extrai rua e número do campo localizacao
        // Formato: "SS: 10/25-070189-01 | END: RUA SEBASTIAO NASCIMENTO, 395, - CHACARA DO CONDE - VILA VELHA | SERVIÇOS NO CAVALETE"
        $rua = '';
        $numero = '';
        
        // Tenta extrair do padrão: END: RUA NOME, NUMERO
        if (preg_match('/END:\s*([^,]+?),\s*(\d+)/', $localizacao, $matches)) {
            $rua = trim($matches[1] ?? '');
            $numero = trim($matches[2] ?? '');
        }
        // Se não encontrou, tenta padrão sem vírgula antes do número
        elseif (preg_match('/END:\s*([A-ZÁÉÍÓÚÇÃÕ\s]+?)\s+(\d+)/', $localizacao, $matches)) {
            $rua = trim($matches[1] ?? '');
            $numero = trim($matches[2] ?? '');
        }
        // Se ainda não encontrou, pega tudo após END: até a primeira vírgula ou hífen
        elseif (preg_match('/END:\s*([^,|-]+)/', $localizacao, $matches)) {
            $enderecoCompleto = trim($matches[1] ?? '');
            // Tenta separar rua e número do endereço completo
            if (preg_match('/^(.+?)\s+(\d+)$/', $enderecoCompleto, $matches2)) {
                $rua = trim($matches2[1] ?? '');
                $numero = trim($matches2[2] ?? '');
            } else {
                $rua = $enderecoCompleto;
            }
        }
        
        $row['rua'] = $rua;
        $row['numero'] = $numero;
    }
    unset($row); // Remove a referência

    // Retornar no formato do DataTables
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => intval($total),
        "recordsFiltered" => intval($filteredTotal),
        "data" => $result,
        'sql' => $sql,
        'total_sql' => $totalSql,
        'filtered_sql' => $filteredSql,
        'where_sql' => $whereSql,
        'where_conditions' => $where,
        'cidade_where_cte' => $cidadeWhereCTE,
        'config_cidade' => $CIDADE_CONFIG,
        'cidades_filtro' => getCidadesAtivas(),
        'sistema_nome' => $SISTEMA_CONFIG['nome'],
        'params' => $params,
        'order_by' => $orderBy,
        'debug_order' => [
            'post_order' => $_POST['order'] ?? null,
            'order_col_idx' => isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : null,
            'order_dir' => isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : null,
            'col_name' => isset($_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['data']) ? $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['data'] : null,
            'allowed_cols' => $allowedCols
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
