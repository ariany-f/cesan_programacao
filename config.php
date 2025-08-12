<?php
// CONFIGURAÇÃO DO SISTEMA DE TAREFAS
// Este arquivo centraliza as configurações para facilitar a criação de cópias

// CONFIGURAÇÃO DE CIDADES
// Defina aqui quais cidades devem ser filtradas
// Opções: 'VILA_VELHA', 'CARIACICA_VIANA', 'GUARAPARI'
$CIDADE_CONFIG = 'VILA_VELHA'; // Mude para 'VILA_VELHA' se necessário

// CONFIGURAÇÃO DE SITUAÇÃO
// Defina aqui qual situação deve ser filtrada
// Opções: 'RETORNADA_DE_CAMPO', 'EM_CAMPO_PENDENTE'
$SITUACAO_CONFIG = 'EM_CAMPO_PENDENTE'; // Mude para 'RETORNADA_DE_CAMPO' se necessário

// Mapeamento de configurações para cidades
$CIDADES_FILTRO = [
    'VILA_VELHA' => ['VILA VELHA'],
    'CARIACICA_VIANA' => ['CARIACICA', 'VIANA'],
    'GUARAPARI' => ['ANCHIETA', 'GUARAPARI', 'PIUMA']
];

// Mapeamento de configurações para situações
$SITUACOES_FILTRO = [
    'RETORNADA_DE_CAMPO' => ['RETORNADA DE CAMPO'],
    'EM_CAMPO_PENDENTE' => ['EM CAMPO', 'PENDENTE DE ENVIO PARA CAMPO']
];

// Dados de conexão com o banco
$DB_CONFIG = [
    'host' => '144.126.141.220',
    'port' => '5432',
    'dbname' => 'umovme_dbview_cesanemerglote2',
    'user' => 'postgres',
    'pass' => 'zJJ9DaMYKC6X3RLfVjxF'
];

// Configurações do sistema
$SISTEMA_CONFIG = [
    'nome' => 'Sistema de Tarefas - ' . $CIDADE_CONFIG,
    'versao' => '1.0.0',
    'cidades' => $CIDADES_FILTRO[$CIDADE_CONFIG] ?? [],
    'situacao' => $SITUACOES_FILTRO[$SITUACAO_CONFIG] ?? []
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

// Função para obter as situações ativas
function getSituacoesAtivas() {
    global $SITUACOES_FILTRO, $SITUACAO_CONFIG;
    return $SITUACOES_FILTRO[$SITUACAO_CONFIG] ?? [];
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

// Função para obter a condição WHERE das situações
function getSituacaoWhereCondition($alias = 't') {
    $situacoes = getSituacoesAtivas();
    if (empty($situacoes)) {
        return null;
    }
    $situacaoConditions = [];
    foreach ($situacoes as $situacao) {
        $situacaoConditions[] = "$alias.tsk_situation ILIKE '$situacao'";
    }
    return "(" . implode(" OR ", $situacaoConditions) . ")";
}
?> 