<?php
// CONFIGURAÇÃO DO SISTEMA DE TAREFAS
// Este arquivo centraliza as configurações para facilitar a criação de cópias

// CONFIGURAÇÃO DE CIDADES
// Defina aqui quais cidades devem ser filtradas
// Opções: 'VILA_VELHA', 'CARIACICA_VIANA', 'GUARAPARI'
$CIDADE_CONFIG = 'CARIACICA_VIANA'; // Mude para 'VILA_VELHA' se necessário

// CONFIGURAÇÃO DE SITUAÇÃO
// REMOVIDO: Filtro pré-fixado de situação - agora filtrado livremente no frontend
// $SITUACAO_CONFIG = 'RETORNADA_DE_CAMPO'; // Comentado para permitir filtro livre

// Mapeamento de configurações para cidades
$CIDADES_FILTRO = [
    'VILA_VELHA' => ['VILA VELHA'],
    'CARIACICA_VIANA' => ['CARIACICA', 'VIANA'],
    'GUARAPARI' => ['ANCHIETA', 'GUARAPARI', 'PIUMA']
];

// Mapeamento de configurações para situações (mantido para referência futura)
$SITUACOES_FILTRO = [
    'RETORNADA_DE_CAMPO' => ['RETORNADA DE CAMPO'],
    'EM_CAMPO_PENDENTE' => ['EM CAMPO', 'PENDENTE DE ENVIO PARA CAMPO']
];


// Fonte de dados dos locais: view materializada (somente leitura).
// Ajuste se no PostgreSQL o nome for outro (ex.: u45468.local_status).
$DB_LOCAL_MV = 'u45468.mv_local';

// Tabela de overrides: status/tags gravados pela aplicação (UPSERT). Exige PK em loc_id.
$DB_LOCAL_OVERRIDES = 'u45468.local_status';


// Dados de conexão com o banco
$DB_CONFIG = [
    'host' => '144.126.141.220',
    'port' => '5432',
    'dbname' => 'umovme_dbview_consglobalmetropole',
    'user' => 'postgres',
    'pass' => 'zJJ9DaMYKC6X3RLfVjxF'
];

// Configurações do sistema
$SISTEMA_CONFIG = [
    'nome' => 'Sistema de Tarefas - ' . $CIDADE_CONFIG,
    'versao' => '1.0.0',
    'cidades' => $CIDADES_FILTRO[$CIDADE_CONFIG] ?? [],
    'situacao' => [] // Removido filtro pré-fixado de situação
];

// Função para obter a string de conexão
function getConnectionString() {
    global $DB_CONFIG;
    return "pgsql:host={$DB_CONFIG['host']};port={$DB_CONFIG['port']};dbname={$DB_CONFIG['dbname']}";
}

// Função para obter as cidades ativas
function getCidadesAtivas() {
    global $CIDADES_FILTRO, $CIDADE_CONFIG;
    return $CIDADES_FILTRO[$CIDADE_CONFIG] ?? [];
}

// Função para obter as situações ativas (desabilitada - filtro livre no frontend)
function getSituacoesAtivas() {
    // Retorna array vazio para não aplicar filtro pré-fixado
    return [];
}

// Função para obter a condição WHERE das cidades
function getCidadeWhereCondition($alias = 'l') {
    $cidades = getCidadesAtivas();
    if (empty($cidades)) {
        return null;
    }
    $cidadeConditions = [];
    foreach ($cidades as $cidade) {
        $cidadeConditions[] = "$alias.e_localidade = '$cidade'";
    }
    return "(" . implode(" OR ", $cidadeConditions) . ")";
}

// Função para obter a condição WHERE das situações (desabilitada - filtro livre no frontend)
function getSituacaoWhereCondition($alias = 't') {
    // Retorna null para não aplicar filtro pré-fixado
    return null;
}
?> 