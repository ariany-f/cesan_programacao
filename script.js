$(document).ready(function() {
    // Exibe o loader global antes de inicializar a tabela
    if ($('#fullpageLoader').length === 0) {
        $('body').append(`
            <div id="fullpageLoader" class="fullpage-loader-bg" style="display:none;">
                <div class="fullpage-loader">
                    <div class="dt-loader"></div>
                    <span>Carregando dados...</span>
                </div>
            </div>
        `);
    }
    $('#fullpageLoader').show();

    // Adiciona botão de refresh ao lado do título
    if ($('#btnRefreshTable').length === 0) {
        $('div.container').prepend('<button id="btnRefreshTable" class="action-btn" style="margin-left:12px;vertical-align:middle;" title="Recarregar"><i class="fa-solid fa-rotate-right"></i></button>');
    }

    // ===== FUNÇÕES AUXILIARES =====
    
    // Função para buscar materiais
    async function buscarMateriais() {
        try {
            const response = await fetch('server.php?materiais=1');
            
            if (!response.ok) {
                throw new Error('Erro na resposta do servidor: ' + response.status);
            }
            
            const materiais = await response.json();
            return materiais;
        } catch (error) {
            console.error('Erro ao buscar materiais:', error);
            return [];
        }
    }

    // Função para aplicar máscara de valor em Real
    function aplicarMascaraMoeda(input) {
        $(input).on('input', function() {
            let valor = this.value.replace(/\D/g, '');
            if (valor) {
                valor = (parseInt(valor) / 100).toFixed(2);
                valor = valor.replace('.', ',');
                valor = valor.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                this.value = 'R$ ' + valor;
            } else {
                this.value = '';
            }
        });
    }

    // ===== GRUPOS DE SERVIÇOS =====
    const gruposServicos = {
        "MANUTENÇÃO": [
            { id: "3300", nome: "SERVIÇOS NO RAMAL DE ÁGUA" },
            { id: "3400", nome: "SERVIÇOS NA REDE DE ÁGUA" },
            { id: "3600", nome: "SERVIÇOS NO CAVALETE" },
            { id: "3700", nome: "SERVIÇOS DIVERS. MANUT. ÁGUA" },
            { id: "3760", nome: "SERVICOS DIVERSOS INTERNOS" },
            { id: "3790", nome: "VAZAMENTO NAO VISIVEL" },
            { id: "3800", nome: "VERIFICAÇÃO DE ABASTECIMENTO" },
            { id: "3800", nome: "VERIFICACAO DE TURBIDEZ" },
            { id: "3995", nome: "RECLAMACAO MANUT ÁGUA" }
        ],
        "COMPLEMENTAR": [
            { id: "3900", nome: "SERVIÇOS COMPLEMENTARES" }
        ],
        "ASFALTO": [
            { id: "3990", nome: "SERVICOS DE PAVIM. ASFALTICA" }
        ],
        "MEDIÇÃO": [
            { id: "8000", nome: "MEDICAO DE SERVICO OPERACIONAL" }
        ]
    };

    // Função para inicializar a tabela
    function initializeTable() {
        
        const table = $('#tasksTable').DataTable({
            "processing": true,
            "serverSide": true,
            "order": [[7, "desc"]],
            "searching": true,
            "pagingType": "full_numbers",
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "ajax": {
                "url": "server.php",
                "type": "POST",
                "data": function(d) {
                    // Adiciona o filtro de aba ativa
                    d.activeTab = window.activeTab || '';
                },
                "dataSrc": function(json) {
                    if (json.data) {
                        json.data = json.data.map(row => {
                            Object.keys(row).forEach(key => {
                                if (row[key] === null) row[key] = "";
                            });
                            return row;
                        });
                    }
                    return json.data;
                },
                "error": function(xhr, error, thrown) {
                    console.error('Erro no DataTable:', error);
                    // Limpa os filtros do DataTable (mantém os visuais no header)
                    const table = $('#tasksTable').DataTable();
                    table.search('');
                    table.columns().every(function() {
                        this.search('');
                    });
                    // Recarrega a tabela
                    table.ajax.reload();
                }
            },
            "columns": [
                {
                    "data": null,
                    "orderable": false,
                    "render": function(data, type, row, meta) {
                        const situacao = (row.situacao || '').toLowerCase();
                        if (situacao === 'cancelada' || situacao === 'retornada de campo') {
                            return '';
                        }else {
                            return `<input type='checkbox' name='selectTask' class='select-task-checkbox' value='${row.tarefa}'>`;
                        }
                    },
                    "width": "30px"
                },
                { "data": "quem" },
                { "data": "ss_numero" },
                { "data": "rua" },
                { "data": "numero" },
                { "data": "cidade" },
                { "data": "bairro" },
                { "data": "setor" },
                { "data": "recepcionado" },
                { "data": "ultima_atividade" },
                { 
                    "data": "situacao",
                    "render": function(data, type, row) {
                        const cls = slugify(row.situacao || '');
                        return `<span class=\"status-badge ${cls}\">${row.situacao}</span>`;
                    }
                },
                { 
                    "data": "status_integracao",
                    "render": function(data, type, row) {
                        const cls = slugify(row.status_integracao || '');
                        return `<span class=\"status-badge ${cls}\">${row.status_integracao}</span>`;
                    }
                },
                { "data": "tarefa" },
                { "data": "prioridade" },
                { "data": "servico" },
                {
                    "data": "tags",
                    "render": function(data, type, row) {
                        if(!row.tags) return '<span style=\"color:#bbb;font-size:11px;\">Sem tags</span>';
                        return `<span class='tag-badge'>${row.tags}</span>`;
                    }
                },
                {
                    "data": null,
                    "orderable": false,
                    "render": function(data, type, row) {
                        let html = `<button class='action-btn status-change' data-id='${row.tarefa}' alt='Alterar status' title='Alterar status'><i class='fa-solid fa-gear'></i></button>`;
                        if (!row.status_integracao) {
                            html += ` <button class='action-btn add-tag' data-id='${row.tarefa}' alt='Adicionar tag' title='Adicionar tag'><i class='fa-solid fa-tag'></i></button>`;
                        }
                        // Botão de prioridade
                        html += ` <button class='action-btn prioridade-change' data-id='${row.tarefa}' alt='Alterar prioridade' title='Alterar prioridade'><i class='fa-solid fa-arrow-up-1-9'></i></button>`;
                        
                        // Botão de incluir itens com contador
                        const numeroItens = parseInt(row.numero_itens) || 0;
                        if (numeroItens > 0) {
                            // Tem itens: azul com fonte branca e contador
                            html += ` <button class='action-btn incluir-itens com-itens' data-id='${row.tarefa}' alt='Incluir Itens (${numeroItens} item${numeroItens > 1 ? 's' : ''})' title='Incluir Itens (${numeroItens} item${numeroItens > 1 ? 's' : ''})'><i class='fa-solid fa-boxes-stacked'></i> ${numeroItens}</button>`;
                        } else {
                            // Não tem itens: cinza com fonte azul (estilo padrão)
                            html += ` <button class='action-btn incluir-itens' data-id='${row.tarefa}' alt='Incluir itens' title='Incluir itens'><i class='fa-solid fa-boxes-stacked'></i></button>`;
                        }
                        
                        if (row.link) {
                            html += ` <button class='action-btn view' data-id='${row.tarefa}' data-link='${row.link}' alt='Ver relatório' title='Ver relatório'><i class='fa-solid fa-eye'></i></button>`;
                        }
                        return html;
                    }
                }
            ],
            "language": {
                "lengthMenu": "Mostrar _MENU_ tarefas por página",
                "zeroRecords": "Nenhuma tarefa encontrada",
                "info": "Mostrando página _PAGE_ de _PAGES_ (_TOTAL_ registros)",
                "infoEmpty": "Nenhuma tarefa disponível",
                "infoFiltered": "(_TOTAL_ filtrados de _MAX_ registros totais)",
                "search": "Pesquisar:",
                "paginate": {
                    "first": "Primeira",
                    "last": "Última",
                    "next": "Próxima",
                    "previous": "Anterior"
                },
                "processing": ""
            },
            "responsive": false,
            "initComplete": function() {
                const table = this.api();
                
                // Garante que a linha de filtros existe
                const $tbody = $('#tasksTable tbody');
                if (!$tbody.find('tr.filter-row').length) {
                    const $filterRow = $('<tr class="filter-row"></tr>');
                    for (let i = 0; i < 17; i++) {
                        $filterRow.append('<th></th>');
                    }
                    $tbody.prepend($filterRow);
                }
                
                // Adiciona campo de entrada para número da página
                function addPageInput() {
                    const $pagination = $('.dataTables_paginate');
                    if ($pagination.find('.page-input').length === 0) {
                        const $pageInput = $('<input type="number" min="1" class="page-input" style="width:50px;margin:0 5px;padding:2px 5px;border:1px solid #e2e8f0;border-radius:4px;text-align:center;">');
                        const $goButton = $('<button class="go-to-page" style="background:#fff;border:1px solid #e2e8f0;border-radius:4px;padding:2px 8px;cursor:pointer;font-size:11.55px;">Ir</button>');
                        
                        // Encontra o botão "Anterior" e insere após ele
                        const $prevButton = $pagination.find('.paginate_button.previous');
                        $prevButton.after($pageInput, $goButton);
                        
                        // Evento do botão "Ir"
                        $goButton.off('click').on('click', function() {
                            const page = parseInt($pageInput.val()) - 1;
                            const totalPages = table.page.info().pages;
                            
                            if (page >= 0 && page < totalPages) {
                                table.page(page).draw('page');
                            } else {
                                showToast('Página inválida!', 'error');
                            }
                        });
                        
                        // Evento de Enter no input
                        $pageInput.off('keypress').on('keypress', function(e) {
                            if (e.which === 13) {
                                $goButton.click();
                            }
                        });
                    }
                }

                // Adiciona o campo inicialmente
                addPageInput();

                // Atualiza o input quando a página muda
                table.on('page.dt', function() {
                    const currentPage = table.page() + 1;
                    $('.page-input').val(currentPage);
                });

                // Adiciona o campo após cada redraw
                table.on('draw.dt', function() {
                    addPageInput();
                });

                // Opções para os selects
                const opcoesStatus = [
                    '',
                    'Duplicada',
                    'Rejeitada',
                    'Baixada no Siscom'
                ];
                // Cria os filtros na linha fora do thead
                const filterRow = $('.filter-row');
                filterRow.find('th').each(function(i) {
                    // Limpa a célula
                    $(this).empty();
                    let input;
                    if (i === 10) { // Situação (dropdown)
                        input = $('<select class="form-control situacao-select" style="width: 100%"></select>');
                        input.append(`<option value=''>Filtrar</option>`);
                        // Carrega todas as situações disponíveis no banco
                        fetch('server.php?situacoes=1')
                            .then(response => response.json())
                            .then(situacoes => {
                                situacoes.forEach(situacao => {
                                    const option = `<option value='${situacao.situacao}'>${situacao.situacao}</option>`;
                                    input.append(option);
                                });
                                input.show();
                            })
                            .catch(error => {
                                // Erro silencioso ao carregar situações
                            });
                    } else if (i === 11) { // Status de Integração
                        input = $('<select class="form-control" style="width: 100%"></select>');
                        opcoesStatus.forEach(opt => {
                            input.append(`<option value='${opt}'>${opt || 'Filtrar'}</option>`);
                        });
                    } else if (i === 13) { // Prioridade
                        input = $('<input class="form-control" type="text" placeholder="Filtrar" style="width: 100%">');
                    } else if (i === 14) { // Serviço Solicitado (dropdown de grupos)
                        input = $('<select class="form-control grupo-servico-select" style="width: 100%"></select>');
                        input.append(`<option value=''>Filtrar</option>`);
                        Object.keys(gruposServicos).forEach(grupo => {
                            input.append(`<option value='${grupo}'>${grupo}</option>`);
                        });
                    } else if (i === 5) { // Cidade (dropdown) - APENAS CIDADES CONFIGURADAS
                        input = $('<select class="form-control cidade-select" style="width: 100%"></select>');
                        input.append(`<option value=''>Filtrar</option>`);
                        // Carrega apenas as cidades configuradas
                        fetch('server.php?cidades=1')
                            .then(response => response.json())
                            .then(cidades => {
                                cidades.forEach(cidade => {
                                    const option = `<option value='${cidade.cidade}'>${cidade.cidade}</option>`;
                                    input.append(option);
                                });
                                input.show();
                            })
                            .catch(error => {
                                // Erro silencioso ao carregar cidades
                            });
                    } else if (i === 8 || i === 9) { // Recepcionado e Última atividade
                        input = $('<input type="text" class="datepicker form-control" placeholder="Filtrar" style="width: 100%">');
                    } else if (i !== 0 && i !== 16) { // Não coloca input no checkbox nem em ações
                        input = $('<input class="form-control" type="text" placeholder="Filtrar" style="width: 100%">');
                    }
                    if (input) {
                        $(this).append(input);
                    }
                });
                // Aplica eventos de filtro
                table.columns().every(function() {
                    const column = this;
                    const colIdx = column.index();
                    const filterCell = $('.filter-row th').eq(colIdx);
                    const input = filterCell.find('input, select');
                    let searchTimeout;
                    let lastValue = input.val();
                    if (colIdx === 14) { // Serviço Solicitado (grupo)
                        input.on('change', function() {
                            const grupo = this.value;
                            let servicos = [];
                            if (grupo && gruposServicos[grupo]) {
                                servicos = gruposServicos[grupo].map(s => s.id + ' - ' + s.nome);
                            }
                            // Envia todos os serviços do grupo como string separada por vírgula
                            const value = servicos.length > 0 ? servicos.join(',') : '';
                            if (value !== lastValue) {
                                lastValue = value;
                                column.search(value).draw();
                            }
                        });
                    } else if (colIdx === 5) { // Cidade (filtro específico)
                        input.on('change', function() {
                            const value = this.value.trim();
                            if (value !== lastValue) {
                                lastValue = value;
                                column.search(value).draw();
                            }
                        });
                    } else if (colIdx === 10) { // Situação (filtro específico)
                        input.on('change', function() {
                            const value = this.value.trim();
                            if (value !== lastValue) {
                                lastValue = value;
                                column.search(value).draw();
                            }
                        });
                    } else if (input.is('select')) {
                        input.on('change', function() {
                            const value = this.value.trim();
                            if (value !== lastValue) {
                                lastValue = value;
                                column.search(value).draw();
                            }
                        });
                    } else if (input.is('input')) {
                        input.on('keyup', function() {
                            const value = this.value.trim();
                            if (value === lastValue) return;
                            lastValue = value;
                            clearTimeout(searchTimeout);
                            if (value) {
                                searchTimeout = setTimeout(() => {
                                    column.search(value).draw();
                                }, 1000);
                            } else {
                                column.search('').draw();
                            }
                        });
                    }
                });
                $('.filter-row .datepicker').each(function() {
                    const $input = $(this);
                    const colIdx = $input.closest('th').index();
                    $input.datepicker({
                        dateFormat: 'dd/mm/yy',
                        changeMonth: true,
                        changeYear: true,
                        onSelect: function(dateText) {
                            const table = $('#tasksTable').DataTable();
                            table.column(colIdx).search(dateText).draw();
                        }
                    });
                });
            },
            "preDrawCallback": function() {
                const $tbody = $('#tasksTable tbody');
                const $filterRow = $tbody.find('tr.filter-row');
                if ($filterRow.length) {
                    $filterRow.detach();
                    $tbody.data('filterRow', $filterRow);
                }
            },
            "drawCallback": function() {
                const $tbody = $('#tasksTable tbody');
                const $filterRow = $tbody.data('filterRow');
                if ($filterRow && !$tbody.find('tr.filter-row').length) {
                    $tbody.prepend($filterRow);
                }
                // Garante que a linha de filtros sempre esteja presente
                if (!$tbody.find('tr.filter-row').length) {
                    const $newFilterRow = $('<tr class="filter-row"></tr>');
                    for (let i = 0; i < 15; i++) {
                        $newFilterRow.append('<th></th>');
                    }
                    $tbody.prepend($newFilterRow);
                }
            }
        });

        // Remove os eventos antigos dos inputs
        $('#tasksTable thead input').off();

        // Evento do botão Ver
        $('#tasksTable').on('click', '.action-btn.view', function() {
            const taskId = $(this).data('id');
            const rowData = table.rows().data().toArray().find(t => t.tarefa === taskId);
            const link = $(this).data('link');
            if (rowData) {
                window.open(link, '_blank');
            }
        });

        // Exibe/oculta overlay de loading global conforme processamento do DataTables
        $('#tasksTable').on('processing.dt', function(e, settings, processing) {
            if (processing) {
                $('#fullpageLoader').fadeIn(120);
            } else {
                $('#fullpageLoader').fadeOut(120);
            }
        });

        // Evento do botão de refresh
        $('#btnRefreshTable').off('click').on('click', function() {
            table.ajax.reload();
        });
    }

    // Inicialização
    initializeTable();

    // ===== CONTROLE DE ABAS =====
    // Variável global para armazenar a aba ativa
    window.activeTab = '';

    // Função para ativar uma aba
    function activateTab(tabName) {
        // Remove a classe active de todas as abas
        $('.tab-btn').removeClass('active');
        
        // Se a aba clicada já estava ativa, desativa
        if (window.activeTab === tabName) {
            window.activeTab = '';
            $('.tab-btn[data-tab="' + tabName + '"]').removeClass('active');
        } else {
            // Garante que "notas-para-baixar" e "notas-baixadas" não podem estar ativas juntas
            if (tabName === 'notas-para-baixar' && window.activeTab === 'notas-baixadas') {
                $('.tab-btn[data-tab="notas-baixadas"]').removeClass('active');
            } else if (tabName === 'notas-baixadas' && window.activeTab === 'notas-para-baixar') {
                $('.tab-btn[data-tab="notas-para-baixar"]').removeClass('active');
            }
            
            // Ativa a nova aba
            window.activeTab = tabName;
            $('.tab-btn[data-tab="' + tabName + '"]').addClass('active');
        }
        
        // Se a aba ativa filtra por situação (recepcao, com-equipes ou notas-para-baixar), limpa o filtro de situação do dropdown
        const table = $('#tasksTable').DataTable();
        if (table && (window.activeTab === 'recepcao' || window.activeTab === 'com-equipes' || window.activeTab === 'notas-para-baixar')) {
            // Limpa o filtro de situação (coluna 10) para evitar conflito
            const situacaoSelect = $('.filter-row th').eq(10).find('.situacao-select');
            if (situacaoSelect.length) {
                situacaoSelect.val('').trigger('change');
            }
            // Limpa também o filtro na coluna do DataTables
            table.column(10).search('');
        }
        
        // Recarrega a tabela com o novo filtro
        if (table) {
            table.ajax.reload();
        }
    }

    // Eventos de clique nas abas
    $(document).on('click', '.tab-btn', function() {
        const tabName = $(this).data('tab');
        activateTab(tabName);
    });

    // Modal de status (garante que está no body)
    function ensureStatusModal() {
        if ($('#statusModal').length === 0) {
            $('body').append(`
                <div id="statusModal" class="custom-modal-bg" style="display:none;">
                    <div class="custom-modal-box">
                        <h3>Alterar status</h3>
                        <select id="statusSelect">
                            <option value="" selected>Selecione...</option>
                            <option value="Baixada no Siscom">Baixada no Siscom</option>
                            <option value="Rejeitada">Rejeitada</option>
                            <option value="Duplicada">Duplicada</option>
                        </select>
                        <div class="custom-modal-actions">
                            <button id="statusCancel" class="cancel">Cancelar</button>
                            <button id="statusConfirm" class="confirm">Confirmar</button>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    // Modal de tag (garante que está no body)
    function ensureTagModal() {
        if ($('#tagModal').length === 0) {
            $('body').append(`
                <div id="tagModal" class="custom-modal-bg" style="display:none;">
                    <div class="custom-modal-box">
                        <h3>Adicionar tag</h3>
                        <input id="tagInput" type="text" placeholder="Digite a tag" />
                        <div class="custom-modal-actions">
                            <button id="tagCancel" class="cancel">Cancelar</button>
                            <button id="tagConfirm" class="confirm">Adicionar</button>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    // Evento do botão de ação para abrir modal de status
    $('#tasksTable').on('click', '.action-btn.status-change', function() {
        ensureStatusModal();
        $('#statusModal').css('display', 'flex');
        const table = $('#tasksTable').DataTable();
        const taskId = $(this).data('id');
        const rowIdx = table.rows().indexes().toArray().find(idx => table.row(idx).data().tarefa === taskId);
        if (rowIdx === undefined) return;
        const rowData = table.row(rowIdx).data();
        $('#statusSelect').val('');
        if (!rowData.situacao) {
            $('#statusSelect').prop('selectedIndex', 0);
        }

        // Remover eventos antigos e garantir atribuição correta
        $(document).off('click', '#statusCancel');
        $(document).off('click', '#statusConfirm');

        // Cancelar
        $(document).on('click', '#statusCancel', function() {
            $('#statusModal').fadeOut(180);
        });
        // Confirmar
        $(document).on('click', '#statusConfirm', async function() {
            const newStatus = $('#statusSelect').val();

            if(!newStatus) {
                showToast('Selecione um status!', 'error');
                return;
            }
            
            let newStatusTexto = newStatus;
            const table = $('#tasksTable').DataTable();
            const taskId = $('#tasksTable .action-btn.status-change.active').data('id');
            let rowIdx = null;
            table.rows().every(function(idx, tableLoop, rowLoop) {
                if (this.data().tarefa == taskId) rowIdx = idx;
            });
            if (rowIdx === null) return;
            const rowData = table.row(rowIdx).data();
            rowData.situacao = newStatus;
            rowData.situacao_texto = newStatusTexto;
            
            // Atualiza também o status de integração com o mesmo valor
            rowData.status_integracao = newStatus;
            
            // Debug: log para verificar se está atualizando
            console.log('Antes da atualização:', {
                tarefa: rowData.tarefa,
                situacao: rowData.situacao,
                status_integracao: rowData.status_integracao
            });
            
            // Atualiza também o status de integração com o mesmo valor
            rowData.status_integracao = newStatus;
            
            console.log('Depois da atualização:', {
                tarefa: rowData.tarefa,
                situacao: rowData.situacao,
                status_integracao: rowData.status_integracao,
                loc_id: rowData.loc_id
            });
            
            // Força o redesenho da tabela
            table.row(rowIdx).data(rowData).draw(false);
            $('#statusModal').fadeOut(180);

            // Monta o XML
            const headers = {
                'Content-Type': 'application/x-www-form-urlencoded'
            };
            const xml = `<schedule>\n  <customFields>\n<situacao><alternativeIdentifier>${newStatus}</alternativeIdentifier></situacao>\n  </customFields>\n</schedule>`;
            const url = `https://api.umov.me/CenterWeb/api/44280e57a1f1ae8ecd723a1cc4f624f34f7c6b/schedule/${rowData.tarefa}.xml`;
            
            try {
                // Primeiro endpoint - schedule
                const response = await fetch(url, {
                    method: 'POST',
                    headers: headers,
                    body: 'data=' + encodeURIComponent(xml)
                });
                
                // Segundo endpoint - serviceLocal (se loc_id estiver disponível)
                let serviceLocalResponse = null;
                if (rowData.loc_id) {
                    const serviceLocalXml = `<serviceLocal>\n  <customFields>\n<situacao><alternativeIdentifier>${newStatus}</alternativeIdentifier></situacao>\n  </customFields>\n</serviceLocal>`;
                    const serviceLocalUrl = `https://api.umov.me/CenterWeb/api/44280e57a1f1ae8ecd723a1cc4f624f34f7c6b/serviceLocal/${rowData.loc_id}.xml`;
                    
                    serviceLocalResponse = await fetch(serviceLocalUrl, {
                        method: 'POST',
                        headers: headers,
                        body: 'data=' + encodeURIComponent(serviceLocalXml)
                    });
                }
                
                if (response.ok && (!serviceLocalResponse || serviceLocalResponse.ok)) {
                    // Atualiza também na tabela dbout_tmp_local2
                    try {
                        const updateFormData = new FormData();
                        updateFormData.append('atualizar_status', '1');
                        updateFormData.append('loc_id', rowData.loc_id);
                        updateFormData.append('novo_status', newStatus);
                        
                        const updateResponse = await fetch('server.php', {
                            method: 'POST',
                            body: updateFormData
                        });
                        
                        const updateResult = await updateResponse.json();
                        
                        if (updateResult.success) {
                            showToast('Status atualizado com sucesso na base de dados!', 'success');
                            
                            // Atualiza a linha com os dados corretos do banco
                            if (updateResult.dados_atualizados) {
                                console.log('Dados atualizados do banco:', updateResult.dados_atualizados);
                                rowData.status_integracao = updateResult.dados_atualizados.e_situacao;
                                table.row(rowIdx).data(rowData).draw(false);
                                console.log('Linha atualizada com novo status_integracao:', rowData.status_integracao);
                            }
                        } else {
                            showToast('Status alterado, mas erro ao atualizar na base de dados.', 'error');
                        }
                    } catch (updateError) {
                        console.error('Erro ao atualizar na base de dados:', updateError);
                        showToast('Status alterado, mas erro ao atualizar na base de dados.', 'error');
                    }
                } else {
                    showToast('Status alterado, mas houve erro ao enviar o XML.', 'error');
                }
            } catch (e) {
                showToast('Status alterado, mas houve erro ao enviar o XML.', 'error');
            }
        });

        // Adiciona/remover classe active no botão de status-change para saber qual linha está sendo editada
        $('.action-btn.status-change').removeClass('active');
        $(this).addClass('active');
    });

    // Evento do botão de ação para abrir modal de tag
    $('#tasksTable').on('click', '.action-btn.add-tag', function() {
        ensureTagModal();
        $('#tagModal').css('display', 'flex');
        $('#tagInput').val('');
        const table = $('#tasksTable').DataTable();
        const taskId = $(this).data('id');
        const rowIdx = table.rows().indexes().toArray().find(idx => table.row(idx).data().tarefa === taskId);
        if (rowIdx === undefined) return;
        const rowData = table.row(rowIdx).data();

        // Remover eventos antigos e garantir atribuição correta
        $(document).off('click', '#tagCancel');
        $(document).off('click', '#tagConfirm');

        // Cancelar
        $(document).on('click', '#tagCancel', function() {
            $('#tagModal').fadeOut(180);
        });
        // Confirmar
        $(document).on('click', '#tagConfirm', async function() {
            const tag = $('#tagInput').val().trim();
            if (!tag) {
                showToast('Digite uma tag!', 'error');
                return;
            }
            if (tag) {
                if (!Array.isArray(rowData.tags)) rowData.tags = [];
                rowData.tags.push(tag);
                table.row(rowIdx).data(rowData).draw();

                // 1. GET do XML atual
                const getUrl = `https://api.umov.me/CenterWeb/api/44280e57a1f1ae8ecd723a1cc4f624f34f7c6b/schedule/${rowData.tarefa}.xml`;
                try {
                    const getResp = await fetch(getUrl);
                    let xmlText = await getResp.text();
                    // 2. Parse o XML e extraia os campos necessários
                    const parser = new DOMParser();
                    const xmlDoc = parser.parseFromString(xmlText, 'application/xml');
                    function getVal(path) {
                        const el = xmlDoc.querySelector(path);
                        return el ? el.textContent : '';
                    }
                    // Campos principais
                    const agentId = getVal('agent > id');
                    const serviceLocalId = getVal('serviceLocal > id');
                    const scheduleTypeAlt = getVal('scheduleType > alternativeIdentifier');
                    const localidade = getVal('customFields > localidade > alternativeIdentifier');
                    const bairro = getVal('customFields > bairro > alternativeIdentifier');
                    const setor = getVal('customFields > setor > alternativeIdentifier');
                    // Data/hora atuais
                    const dateGet = getVal('date');
                    const hourGet = getVal('hour');
                    const now = new Date();
                    const date = dateGet || now.toISOString().slice(0,10);
                    const hour = hourGet || now.toTimeString().slice(0,5);
                    // 3. Montar novo XML enxuto
                    let newXml = `<schedule>\n`;
                    if(agentId) newXml += `  <agent><id>${agentId}</id></agent>\n`;
                    if(serviceLocalId) newXml += `  <serviceLocal><id>${serviceLocalId}</id></serviceLocal>\n`;
                    if(scheduleTypeAlt) newXml += `  <scheduleType><alternativeIdentifier>${scheduleTypeAlt}</alternativeIdentifier></scheduleType>\n`;
                    newXml += `  <activitiesOrigin>3</activitiesOrigin>\n`;
                    newXml += `  <situation><id>30</id></situation>\n`;
                    newXml += `  <date>${date}</date>\n`;
                    newXml += `  <hour>${hour}</hour>\n`;
                    newXml += `  <customFields>\n`;
                    if(localidade) newXml += `    <localidade><alternativeIdentifier>${localidade}</alternativeIdentifier></localidade>\n`;
                    if(bairro) newXml += `    <bairro><alternativeIdentifier>${bairro}</alternativeIdentifier></bairro>\n`;
                    if(setor) newXml += `    <setor><alternativeIdentifier>${setor}</alternativeIdentifier></setor>\n`;
                    newXml += `    <tag>${tag}</tag>\n`;
                    newXml += `  </customFields>\n`;
                    newXml += `</schedule>`;
                    // 4. POST para o endpoint
                    const postUrl = 'https://api.umov.me/CenterWeb/api/44280e57a1f1ae8ecd723a1cc4f624f34f7c6b/schedule.xml';
                    const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
                    const postResp = await fetch(postUrl, {
                        method: 'POST',
                        headers: headers,
                        body: 'data=' + encodeURIComponent(newXml)
                    });
                    
                    // Segundo endpoint - serviceLocal (se loc_id estiver disponível)
                    let serviceLocalResponse = null;
                    if (rowData.loc_id) {
                        const serviceLocalXml = `<serviceLocal>\n  <customFields>\n<tag>${tag}</tag>\n  </customFields>\n</serviceLocal>`;
                        const serviceLocalUrl = `https://api.umov.me/CenterWeb/api/44280e57a1f1ae8ecd723a1cc4f624f34f7c6b/serviceLocal/${rowData.loc_id}.xml`;
                        
                        serviceLocalResponse = await fetch(serviceLocalUrl, {
                            method: 'POST',
                            headers: headers,
                            body: 'data=' + encodeURIComponent(serviceLocalXml)
                        });
                    }
                    
                    if (postResp.ok && (!serviceLocalResponse || serviceLocalResponse.ok)) {
                        // Atualiza também na tabela dbout_tmp_local2
                        try {
                            const updateFormData = new FormData();
                            updateFormData.append('atualizar_tags', '1');
                            updateFormData.append('loc_id', rowData.loc_id);
                            updateFormData.append('nova_tag', tag);
                            
                            const updateResponse = await fetch('server.php', {
                                method: 'POST',
                                body: updateFormData
                            });
                            
                            const updateResult = await updateResponse.json();
                            
                            if (updateResult.success) {
                                showToast('Tag adicionada com sucesso na base de dados!', 'success');
                                
                                // Atualiza a linha com os dados corretos do banco
                                if (updateResult.dados_atualizados) {
                                    console.log('Tags atualizadas do banco:', updateResult.dados_atualizados);
                                    rowData.tags = updateResult.dados_atualizados.e_tag;
                                    table.row(rowIdx).data(rowData).draw(false);
                                    console.log('Linha atualizada com novas tags:', rowData.tags);
                                }
                            } else {
                                showToast('Tag adicionada, mas erro ao atualizar na base de dados.', 'error');
                            }
                        } catch (updateError) {
                            console.error('Erro ao atualizar tags na base de dados:', updateError);
                            showToast('Tag adicionada, mas erro ao atualizar na base de dados.', 'error');
                        }
                    } else {
                        showToast('Tag adicionada, mas houve erro ao enviar o XML.', 'error');
                    }
                } catch (e) {
                    showToast('Tag adicionada, mas houve erro ao enviar o XML.', 'error');
                }
            }
            $('#tagModal').fadeOut(180);
        });
    });

    // Função para buscar agentes e preencher o dropdown
    async function carregarAgentes() {
        const $select = $('#agentSelect');
        $select.hide();
        if ($('#agentLoading').length === 0) {
            $select.before('<div id="agentLoading" style="text-align:center; margin: 18px 0;"><div class="dt-loader" style="width:28px;height:28px;margin:0 auto;"></div></div>');
        } else {
            $('#agentLoading').show();
        }
        $select.empty();
        $select.append('<option value="">Selecione...</option>');
        try {
            const resp = await fetch('server.php?agentes=1');
            const agentes = await resp.json();
            agentes.forEach(ag => {
                $select.append(`<option value="${ag.age_id}">${ag.age_name} (${ag.age_login})</option>`);
            });
            $('#agentLoading').hide();
            $select.show();
        } catch (e) {
            $('#agentLoading').html('<span style="color:#d00">Erro ao carregar agentes</span>');
        }
    }

    // Botão Transferir e modal
    if ($('#transferModal').length === 0) {
        $('body').append(`
            <div id="transferModal" class="custom-modal-bg" style="display:none;">
                <div class="custom-modal-box">
                    <h3>Transferir tarefas</h3>
                    <div id="transferCount" style="font-size: 14px; margin-bottom: 12px; font-weight: 500;"></div>
                    <label for="agentSelect">Selecione o agente:</label>
                    <select id="agentSelect" style="width:100%;margin-bottom:18px;"></select>
                    <div class="custom-modal-actions">
                        <button id="transferClearSelection" class="cancel" style="background:#f9e7e7;color:#b00;">Remover Seleção</button>
                        <button id="transferCancel" class="cancel">Cancelar</button>
                        <button id="transferConfirm" class="confirm">Confirmar</button>
                    </div>
                </div>
            </div>
        `);
    }

    $('#btnTransferir').on('click', function() {
        const checked = $('.select-task-checkbox:checked').length;
        $('#transferCount').text(checked === 1 ? '1 tarefa selecionada' : `${checked} tarefas selecionadas`);
        carregarAgentes().then(() => {
            // Seleção automática do agente se todas as tarefas selecionadas tiverem o mesmo 'quem'
            const table = $('#tasksTable').DataTable();
            const selectedTasks = $('.select-task-checkbox:checked').map(function() { return $(this).val(); }).get();
            let quemSet = new Set();
            let quemValue = '';
            selectedTasks.forEach(tskId => {
                const row = table.rows().data().toArray().find(r => r.tarefa == tskId);
                if (row && row.quem) {
                    quemSet.add(row.quem);
                    quemValue = row.quem;
                }
            });
            if (quemSet.size === 1 && quemValue) {
                // Seleciona no dropdown o agente correspondente ao nome
                const $select = $('#agentSelect');
                const opt = $select.find('option').filter(function() {
                    return $(this).text().trim().toUpperCase().startsWith(quemValue.trim().toUpperCase());
                }).first();
                if (opt.length) {
                    $select.val(opt.val());
                }
            } else {
                $('#agentSelect').val('');
            }
        });
        $('#transferModal').css('display', 'flex');
    });

    $(document).off('click', '#transferClearSelection');
    $(document).on('click', '#transferClearSelection', function() {
        $('#agentSelect').val('');
    });

    $(document).off('click', '#transferCancel');

    $(document).on('click', '#transferCancel', function() {
        $('#transferModal').fadeOut(180);
    });

    $(document).off('click', '#transferConfirm');

    $(document).on('click', '#transferConfirm', async function() {
        const agentId = $('#agentSelect').val();
        if (!agentId) {
            showToast('Selecione um agente!', 'error');
            return;
        }
        const selectedTasks = $('.select-task-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (selectedTasks.length === 0) {
            showToast('Selecione pelo menos uma tarefa!', 'error');
            return;
        }
        // Mostra loader global
        $('#fullpageLoader').fadeIn(120);
        let success = 0, fail = 0;
        for (const tsk_id of selectedTasks) {
            const url = `https://api.umov.me/CenterWeb/api/44280e57a1f1ae8ecd723a1cc4f624f34f7c6b/schedule/${tsk_id}.xml`;
            const xml = `<schedule><agent><id>${agentId}</id></agent></schedule>`;
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'data=' + encodeURIComponent(xml)
                });
                if (resp.ok) success++;
                else fail++;
            } catch (e) { fail++; }
        }
        $('#fullpageLoader').fadeOut(120);
        showToast(`Transferência concluída! Sucesso: ${success}, Falha: ${fail}`, fail === 0 ? 'success' : 'error');
        $('#transferModal').fadeOut(180);
    });

    // Selecionar checkbox ao clicar na linha (exceto em botões/links)
    $('#tasksTable tbody').on('click', 'tr', function(e) {
        // Ignora se clicou em botão, link ou input
        if ($(e.target).is('button, a, input')) return;
        const $checkbox = $(this).find('.select-task-checkbox');
        if ($checkbox.length) {
            $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
        }
    });

    // Função para ativar/desativar o botão Transferir conforme seleção
    function atualizarBotaoTransferir() {
        const checked = $('.select-task-checkbox:checked').length;
        $('#btnTransferir').prop('disabled', checked === 0);
    }
    // Atualiza ao carregar a tabela e ao clicar nos checkboxes
    $(document).on('change', '.select-task-checkbox', atualizarBotaoTransferir);

    $(document).ready(function() {
        atualizarBotaoTransferir();
    });

    // Após inicializar a tabela, adiciona botão de limpar filtro ao lado da barra de pesquisa
    $(document).on('init.dt', function(e, settings) {
        if ($('#btnClearFilters').length === 0) {
            // Aguarda a barra de pesquisa ser renderizada
            setTimeout(function() {
                const $searchDiv = $('#tasksTable_filter');
                if ($searchDiv.length) {
                    $searchDiv.append('<button id="btnClearFilters" class="action-btn" style="margin-left:8px;vertical-align:middle;" title="Limpar filtros"><i class="fa-solid fa-filter-circle-xmark"></i></button>');
                }
            }, 100);
        }
    });

    // Evento do botão de limpar filtros
    $(document).on('click', '#btnClearFilters', function() {
        const table = $('#tasksTable').DataTable();
        // Limpa filtros globais e de coluna (exceto cidade e situação)
        table.search('');
        table.columns().every(function(colIdx) {
            if (colIdx !== 5 && colIdx !== 10) { // Não limpa o filtro da coluna cidade (índice 5) e situação (índice 10)
                this.search('');
            }
        });
        // Limpa inputs, selects e datepickers (exceto cidade e situação)
        $('.filter-row input, .filter-row select').each(function() {
            const colIdx = $(this).closest('th').index();
            if (colIdx !== 5 && colIdx !== 10) { // Não limpa o select de cidade e situação
                if ($(this).is('select')) {
                    $(this).val('');
                } else {
                    $(this).val('');
                }
            }
        });
        
        // Garante que a linha de filtros permaneça
        const $tbody = $('#tasksTable tbody');
        let $filterRow = $tbody.find('tr.filter-row');
        if ($filterRow.length) {
            $filterRow.detach();
            $tbody.data('filterRow', $filterRow);
        }
        
        table.draw(false); // Redesenha sem perder paginação (mantém filtros de cidade e situação)
        
        // Após o draw, garante que a linha de filtro está presente
        setTimeout(function() {
            const $tbody = $('#tasksTable tbody');
            let $filterRow = $tbody.data('filterRow');
            if ($filterRow && !$tbody.find('tr.filter-row').length) {
                $tbody.prepend($filterRow);
            }
            // Se ainda não há linha de filtros, cria uma nova
            if (!$tbody.find('tr.filter-row').length) {
                const $newFilterRow = $('<tr class="filter-row"></tr>');
                for (let i = 0; i < 15; i++) {
                    $newFilterRow.append('<th></th>');
                }
                $tbody.prepend($newFilterRow);
            }
        }, 100);
    });

    // Modal de prioridade (garante que está no body)
    function ensurePrioridadeModal() {
        if ($('#prioridadeModal').length === 0) {
            $('body').append(`
                <div id="prioridadeModal" class="custom-modal-bg" style="display:none;">
                    <div class="custom-modal-box">
                        <h3>Alterar prioridade</h3>
                        <input id="prioridadeInput" type="number" min="0" max="999" placeholder="Digite a prioridade" />
                        <div class="custom-modal-actions">
                            <button id="prioridadeCancel" class="cancel">Cancelar</button>
                            <button id="prioridadeConfirm" class="confirm">Confirmar</button>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    // Evento do botão de ação para abrir modal de prioridade
    $('#tasksTable').on('click', '.action-btn.prioridade-change', function() {
        ensurePrioridadeModal();
        $('#prioridadeModal').css('display', 'flex');
        const table = $('#tasksTable').DataTable();
        const taskId = $(this).data('id');
        const rowIdx = table.rows().indexes().toArray().find(idx => table.row(idx).data().tarefa === taskId);
        if (rowIdx === undefined) return;
        const rowData = table.row(rowIdx).data();
        $('#prioridadeInput').val(rowData.prioridade || '');
        // Remover eventos antigos e garantir atribuição correta
        $(document).off('click', '#prioridadeCancel');
        $(document).off('click', '#prioridadeConfirm');
        // Cancelar
        $(document).on('click', '#prioridadeCancel', function() {
            $('#prioridadeModal').fadeOut(180);
        });
        // Confirmar
        $(document).on('click', '#prioridadeConfirm', async function() {
            const novaPrioridade = $('#prioridadeInput').val();
            if (novaPrioridade === '' || isNaN(novaPrioridade)) {
                showToast('Digite uma prioridade válida!', 'error');
                return;
            }
            rowData.prioridade = novaPrioridade;
            table.row(rowIdx).data(rowData).draw();
            $('#prioridadeModal').fadeOut(180);
            // Monta o XML
            const headers = {
                'Content-Type': 'application/x-www-form-urlencoded'
            };
            const xml = `<schedule>\n<priority>${novaPrioridade}</priority>\n </schedule>`;
            const url = `https://api.umov.me/CenterWeb/api/44280e57a1f1ae8ecd723a1cc4f624f34f7c6b/schedule/${rowData.tarefa}.xml`;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: headers,
                    body: 'data=' + encodeURIComponent(xml)
                });
                if (response.ok) {
                    showToast('Prioridade alterada e XML enviado com sucesso!', 'success');
                } else {
                    showToast('Prioridade alterada, mas houve erro ao enviar o XML.', 'error');
                }
            } catch (e) {
                showToast('Prioridade alterada, mas houve erro ao enviar o XML.', 'error');
            }
        });
        // Adiciona/remover classe active no botão de prioridade-change para saber qual linha está sendo editada
        $('.action-btn.prioridade-change').removeClass('active');
        $(this).addClass('active');
    });

    // Adiciona botão de selecionar todos e contador de seleção no header da tabela
    $(document).ready(function() {
        // Botão de selecionar todos
        if ($('#selectAllTasks').length === 0) {
            $('#tasksTable thead tr.header-row th').eq(0).append('<input type="checkbox" id="selectAllTasks" title="Selecionar todos" style="margin-left:2px;vertical-align:middle;">');
        }
        // Mostrador de tarefas selecionadas
        if ($('#selectedCount').length === 0) {
            $('#btnTransferir').after('<span id="selectedCount" style="margin-left:12px;font-size:13px;color:#1976d2;font-weight:600;vertical-align:middle;">0 selecionadas</span>');
        }
    });

    // Função para atualizar contador de selecionados
    function atualizarContadorSelecionados() {
        const count = $('.select-task-checkbox:checked').length;
        $('#selectedCount').text(count + ' selecionadas');
        $('#btnTransferir').prop('disabled', count === 0);
    }

    // Evento do botão de selecionar todos
    $(document).on('change', '#selectAllTasks', function() {
        const checked = $(this).is(':checked');
        // Marca/desmarca apenas os checkboxes visíveis (filtrados)
        $('#tasksTable tbody tr:visible .select-task-checkbox').prop('checked', checked).trigger('change');
        atualizarContadorSelecionados();
    });

    // Atualiza contador ao selecionar/desmarcar individualmente
    $(document).on('change', '.select-task-checkbox', function() {
        atualizarContadorSelecionados();
        // Atualiza o estado do selectAllTasks
        const total = $('#tasksTable tbody tr:visible .select-task-checkbox').length;
        const checked = $('#tasksTable tbody tr:visible .select-task-checkbox:checked').length;
        $('#selectAllTasks').prop('checked', total > 0 && total === checked);
    });

    // Atualiza contador ao carregar a tabela
    $(document).on('draw.dt', function() {
        atualizarContadorSelecionados();
        // Atualiza o estado do selectAllTasks
        const total = $('#tasksTable tbody tr:visible .select-task-checkbox').length;
        const checked = $('#tasksTable tbody tr:visible .select-task-checkbox:checked').length;
        $('#selectAllTasks').prop('checked', total > 0 && total === checked);
    });

    // ===== MODAIS DE ITENS =====

    // Modal de incluir itens (garante que está no body)
    function ensureIncluirItensModal() {
        if ($('#incluirItensModal').length === 0) {
            $('body').append(`
                <div id="incluirItensModal" class="custom-modal-bg" style="display:none;">
                    <div class="custom-modal-box" style="width: 800px; max-width: 95vw;">
                        <h3>Itens da Tarefa</h3>
                        
                        <div style="margin-bottom: 15px; text-align: right;">
                            <button id="btnAdicionarItem" class="action-btn transfer-btn">
                                <i class="fa-solid fa-plus"></i>&nbsp;Adicionar Item
                            </button>
                        </div>
                        
                        <table id="itensTable" class="display compact" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>UN</th>
                                    <th>Quantidade</th>
                                    <th>Valor Total</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dados serão carregados aqui -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align: right;">Total:</th>
                                    <th style="text-align: left; color: #1976d2;"></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <div class="custom-modal-actions" style="margin-top: 20px;">
                            <button id="incluirItensClose" class="cancel">Fechar</button>
                        </div>
                    </div>
                </div>
            `);
        }
        
        // Cria o loading fora do modal
        if ($('#itensLoading').length === 0) {
            $('body').append(`
                <div id="itensLoading" class="fullpage-loader-bg" style="display:none;z-index:9998;">
                    <div class="fullpage-loader">
                        <div class="dt-loader"></div>
                        <span>Carregando itens...</span>
                    </div>
                </div>
            `);
        } else {
            $('#itensLoading').show();
        }
    }

    // Modal de adicionar novo item
    function ensureAdicionarItemModal() {
        if ($('#adicionarItemModal').length === 0) {
            $('body').append(`
                <div id="adicionarItemModal" class="custom-modal-bg" style="display:none;">
                    <div class="custom-modal-box" style="width: 500px;">
                        <h3>Adicionar Item</h3>
                        <div style="margin-bottom: 15px;">
                            <label for="materialSelect" style="display: block; margin-bottom: 5px; font-weight: 500; color: #333;">Material:</label>
                            <select id="materialSelect" style="width:100%;margin-bottom:10px;">
                                <option value="">Carregando materiais...</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="unInput" style="display: block; margin-bottom: 5px; font-weight: 500; color: #333;">UN:</label>
                            <input id="unInput" type="text" placeholder="Unidade" style="width:100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; height: 38px;" />
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="qtdInput" style="display: block; margin-bottom: 5px; font-weight: 500; color: #333;">Quantidade:</label>
                            <input id="qtdInput" type="text" placeholder="Quantidade" value="1" style="width:100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; height: 38px;" />
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="valorTotalInput" style="display: block; margin-bottom: 5px; font-weight: 500; color: #333;">Valor Total:</label>
                            <input id="valorTotalInput" type="text" placeholder="R$ 0,00" style="width:100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; height: 38px;" />
                        </div>
                        <div class="custom-modal-actions">
                            <button id="adicionarItemCancel" class="cancel">Cancelar</button>
                            <button id="adicionarItemConfirm" class="confirm">Adicionar</button>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    // Evento do botão incluir itens
    $('#tasksTable').on('click', '.action-btn.incluir-itens', async function() {
        ensureIncluirItensModal();
        ensureAdicionarItemModal();
        const taskId = $(this).data('id');
        
        // Busca os dados da linha atual para pegar a SS
        const table = $('#tasksTable').DataTable();
        const rowData = table.rows().data().toArray().find(row => row.tarefa == taskId);
        
        if (!rowData) {
            showToast('Não foi possível encontrar os dados da tarefa!', 'error');
            return;
        }
        
        const ss = rowData.ss;
        if (!ss) {
            showToast('Não foi possível encontrar a SS da tarefa!', 'error');
            return;
        }
        
        // Desabilita o botão Adicionar Item enquanto carrega
        $('#btnAdicionarItem').prop('disabled', true).text('Carregando...');
        
        // Mostra apenas o loading (não o modal ainda)
        $('#itensLoading').show();
        
        // Destroi a tabela anterior se existir
        if ($.fn.DataTable.isDataTable('#itensTable')) {
            $('#itensTable').DataTable().destroy();
        }
        
        // Inicializa o datatable de itens
        $('#itensTable').DataTable({
            "processing": false,
            "ajax": {
                "url": `server.php?itens=1&ss=${encodeURIComponent(ss)}`,
                "type": "GET",
                "dataSrc": function(json) {
                    if (json.error) {
                        console.error('Erro ao carregar itens:', json.error);
                        return [];
                    }
                    return json || [];
                }
            },
            "columns": [
                { "data": "material" },
                { "data": "unidade" },
                { 
                    "data": "quantidade",
                    "render": function(data, type, row) {
                        return parseFloat(data || 0).toFixed(4);
                    }
                },
                { 
                    "data": "valor_total",
                    "render": function(data, type, row) {
                        return 'R$ ' + parseFloat(data || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    }
                },
                {
                    "data": null,
                    "orderable": false,
                    "render": function(data, type, row) {
                        return `<button class='action-btn excluir-item' data-id='${row.id}' style='background:#e53935;border-color:#d32f2f;color:white;' title='Excluir item'><i class='fa-solid fa-trash' style='color:white;'></i></button>`;
                    }
                }
            ],
            "footerCallback": function(row, data, start, end, display) {
                const api = this.api();
                // Calcula a soma total dos valores (usando os dados brutos)
                const total = data.reduce(function(acc, item) {
                    const valor = parseFloat(item.valor_total) || 0;
                    return acc + valor;
                }, 0);
                // Atualiza o footer existente
                $(api.column(3).footer()).html('<span style="color:#1976d2;font-weight:700;">R$ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '</span>');
            },
            "language": {
                "lengthMenu": "Mostrar _MENU_ itens por página",
                "zeroRecords": "Nenhum item encontrado",
                "info": "Mostrando página _PAGE_ de _PAGES_ (_TOTAL_ registros)",
                "infoEmpty": "Nenhum item adicionado",
                "search": "Pesquisar:",
                "paginate": {
                    "first": "Primeira",
                    "last": "Última",
                    "next": "Próxima",
                    "previous": "Anterior"
                }
            },
            "pageLength": 10,
            "lengthMenu": [[5, 10, 25], [5, 10, 25]],
            "order": [[0, "asc"]],
            "initComplete": function() {
                // Oculta loading
                $('#itensLoading').fadeOut(200);
                
                // Habilita o botão Adicionar Item quando os itens terminam de carregar
                $('#btnAdicionarItem').prop('disabled', false).html('<i class="fa-solid fa-plus"></i>&nbsp;Adicionar Item');
                
                // Só agora mostra o modal
                $('#incluirItensModal').css('display', 'flex');
            }
        });
        
        // Evento do botão Adicionar Item
        $(document).off('click', '#btnAdicionarItem');
        $(document).on('click', '#btnAdicionarItem', async function() {
            // Mostra loading imediatamente
            if ($('#adicionarItemLoading').length === 0) {
                $('body').append(`
                    <div id="adicionarItemLoading" class="fullpage-loader-bg" style="display:flex;z-index:9999;">
                        <div class="fullpage-loader">
                            <div class="dt-loader"></div>
                            <span>Carregando materiais...</span>
                        </div>
                    </div>
                `);
            } else {
                $('#adicionarItemLoading').show();
            }
            
            // Carrega materiais
            const materiais = await buscarMateriais();
            console.log('Materiais carregados:', materiais);
            
            const $materialSelect = $('#materialSelect');
            $materialSelect.empty();
            $materialSelect.append('<option value="">Selecione um material...</option>');
            
            if (Array.isArray(materiais) && materiais.length > 0) {
                // Adiciona um contador de materiais carregados
                console.log(`Carregando ${materiais.length} materiais...`);
                
                materiais.forEach(material => {
                    const valorFormatado = material.valor_unitario && material.valor_unitario > 0 ? 
                        `R$ ${parseFloat(material.valor_unitario).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}` : 
                        'Valor não informado';
                    const unidade = material.unidade || 'UN';
                    const optionText = `${material.nome} (${unidade} - ${valorFormatado})`;
                    $materialSelect.append(`<option value="${material.id}" data-unidade="${unidade}" data-valor="${material.valor_unitario || 0}">${optionText}</option>`);
                });
                
                // Adiciona dica de uso se houver muitos materiais
                if (materiais.length > 50) {
                    $materialSelect.after(`
                        <div style="margin-top: 5px; font-size: 12px; color: #666; text-align: center;">
                            💡 Dica: Digite para filtrar rapidamente entre ${materiais.length} materiais disponíveis
                        </div>
                    `);
                }
            } else {
                console.log('Nenhum material encontrado ou resposta inválida');
            }
            
            console.log('Opções no select antes do Select2:', $materialSelect.find('option').length);
            
            // Destroi o Select2 se já existir
            if ($materialSelect.hasClass('select2-hidden-accessible') && typeof $.fn.select2 !== 'undefined') {
                $materialSelect.select2('destroy');
            }
            
            // Aguarda um momento para garantir que o DOM foi atualizado
            setTimeout(() => {
                // Verifica se o Select2 está disponível
                console.log('Verificando Select2:', typeof $.fn.select2);
                console.log('jQuery disponível:', typeof $);
                if (typeof $.fn.select2 !== 'undefined') {
                    // Inicializa o Select2 com configurações básicas
                    $materialSelect.select2({
                        placeholder: 'Digite para buscar um material...',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#adicionarItemModal'),
                        minimumInputLength: 0,
                        minimumResultsForSearch: 0,
                        language: {
                            noResults: function() {
                                return "Nenhum material encontrado";
                            },
                            searching: function() {
                                return "Buscando...";
                            },
                            removeAllItems: function() {
                                return "Remover todos os itens";
                            }
                        }
                    });
                    
                    console.log('Select2 inicializado com sucesso');
                    
                    // Testa se o Select2 está funcionando
                    $materialSelect.on('select2:open', function() {
                        console.log('Dropdown do Select2 aberto');
                    });
                    
                    // Garante que o botão de limpar funcione
                    $materialSelect.on('select2:select', function() {
                        console.log('Item selecionado');
                    });
                    
                    $materialSelect.on('select2:clear', function() {
                        console.log('Seleção limpa');
                    });
                    
                    // Adiciona evento de clique manual para o botão de limpar
                    $(document).on('click', '.select2-selection__clear', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $materialSelect.val('').trigger('change');
                        console.log('Botão de limpar clicado manualmente');
                    });
                } else {
                    console.log('Select2 não disponível, usando select nativo');
                }
            }, 100);
            
            // Adiciona estilos específicos para garantir que o dropdown apareça
            $('<style>')
                .prop('type', 'text/css')
                .html(`
                    /* Container principal do Select2 */
                    .select2-container {
                        width: 100% !important;
                        display: block !important;
                    }
                    
                    /* Container quando aberto */
                    .select2-container--open {
                        z-index: 10000 !important;
                    }
                    
                    /* Seleção principal */
                    .select2-selection {
                        height: 38px !important;
                        border: 1px solid #ccc !important;
                        border-radius: 4px !important;
                        background-color: #fff !important;
                        transition: border-color 0.2s ease !important;
                        position: relative !important;
                        padding-right: 50px !important;
                    }
                    
                    .select2-selection:focus,
                    .select2-selection--single:focus {
                        border-color: #1976d2 !important;
                        outline: none !important;
                        box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1) !important;
                    }
                    
                    /* Texto renderizado na seleção */
                    .select2-selection__rendered {
                        padding: 8px 12px !important;
                        padding-right: 40px !important;
                        font-size: 14px !important;
                        line-height: 20px !important;
                        color: #333 !important;
                        overflow: hidden !important;
                        text-overflow: ellipsis !important;
                        white-space: nowrap !important;
                    }
                    
                    /* Seta do dropdown */
                    .select2-selection__arrow {
                        height: 36px !important;
                        width: 20px !important;
                        right: 6px !important;
                    }
                    
                    /* Botão de limpar */
                    .select2-selection__clear {
                        color: #999 !important;
                        font-size: 18px !important;
                        margin-right: 8px !important;
                        cursor: pointer !important;
                        position: absolute !important;
                        right: 30px !important;
                        top: 50% !important;
                        transform: translateY(-50%) !important;
                        z-index: 10 !important;
                        background: rgba(255, 255, 255, 0.9) !important;
                        border-radius: 50% !important;
                        width: 20px !important;
                        height: 20px !important;
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        transition: all 0.2s ease !important;
                    }
                    
                    .select2-selection__clear:hover {
                        color: #e53935 !important;
                        background: rgba(229, 57, 53, 0.1) !important;
                        transform: translateY(-50%) scale(1.1) !important;
                    }
                    
                    .select2-selection__clear:before {
                        content: "×" !important;
                        font-weight: bold !important;
                        line-height: 1 !important;
                    }
                    
                    /* Garante que o botão de limpar seja visível */
                    .select2-selection--single .select2-selection__clear {
                        display: block !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                    }
                    
                    /* Esconde o botão quando não há seleção */
                    .select2-selection--single:not(.select2-selection--single[title]) .select2-selection__clear {
                        display: none !important;
                    }
                    
                    /* Dropdown */
                    .select2-dropdown {
                        z-index: 10000 !important;
                        border: 1px solid #1976d2 !important;
                        border-radius: 4px !important;
                        box-shadow: 0 4px 12px rgba(25, 118, 210, 0.15) !important;
                        background: #fff !important;
                    }
                    
                    /* Campo de pesquisa */
                    .select2-search__field {
                        padding: 8px 12px !important;
                        font-size: 14px !important;
                        border: 1px solid #ddd !important;
                        border-radius: 4px !important;
                        outline: none !important;
                        width: 100% !important;
                        box-sizing: border-box !important;
                    }
                    
                    .select2-search__field:focus {
                        border-color: #1976d2 !important;
                        box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1) !important;
                    }
                    
                    /* Container da pesquisa */
                    .select2-search {
                        padding: 8px !important;
                        border-bottom: 1px solid #f0f0f0 !important;
                    }
                    
                    /* Opções do resultado */
                    .select2-results__option {
                        padding: 10px 12px !important;
                        font-size: 14px !important;
                        border-bottom: 1px solid #f8f9fa !important;
                        cursor: pointer !important;
                        transition: background-color 0.2s ease !important;
                    }
                    
                    .select2-results__option:last-child {
                        border-bottom: none !important;
                    }
                    
                    .select2-results__option:hover {
                        background-color: #f8f9fa !important;
                    }
                    
                    .select2-results__option--highlighted {
                        background-color: #1976d2 !important;
                        color: white !important;
                    }
                    
                    .select2-results__option--highlighted small {
                        color: rgba(255, 255, 255, 0.8) !important;
                    }
                    
                    /* Mensagem de "nenhum resultado" */
                    .select2-results__message {
                        padding: 10px 12px !important;
                        font-size: 14px !important;
                        color: #666 !important;
                        font-style: italic !important;
                    }
                    
                    /* Estilos para o select nativo como fallback */
                    #materialSelect {
                        width: 100% !important;
                        padding: 8px 12px !important;
                        border: 1px solid #ccc !important;
                        border-radius: 4px !important;
                        font-size: 14px !important;
                        height: 38px !important;
                        box-sizing: border-box !important;
                    }
                    
                    /* Estilos para os inputs do modal */
                    #unInput:focus,
                    #qtdInput:focus,
                    #valorTotalInput:focus {
                        border-color: #1976d2 !important;
                        outline: none !important;
                        box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1) !important;
                    }
                    
                    /* Melhorar espaçamento do modal */
                    .custom-modal-box {
                        padding: 20px !important;
                    }
                    
                    .custom-modal-box h3 {
                        margin-bottom: 20px !important;
                        color: #333 !important;
                        font-size: 18px !important;
                        font-weight: 600 !important;
                    }
                `)
                .appendTo('head');
            
            // Aplica máscara no campo valor
            aplicarMascaraMoeda('#valorTotalInput');
            
            // Evento para preencher automaticamente a unidade e calcular valor total
            $materialSelect.off('change').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const unidade = selectedOption.data('unidade');
                const valorUnitario = parseFloat(selectedOption.data('valor')) || 0;
                
                // Preenche a unidade automaticamente
                $('#unInput').val(unidade);
                
                // Calcula o valor total baseado na quantidade
                const quantidade = parseFloat($('#qtdInput').val()) || 0;
                const valorTotal = quantidade * valorUnitario;
                
                if (valorTotal > 0) {
                    const valorFormatado = valorTotal.toFixed(2).replace('.', ',');
                    $('#valorTotalInput').val('R$ ' + valorFormatado.replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
                }
            });
            
            // Evento para recalcular valor total quando a quantidade mudar
            $('#qtdInput').off('input').on('input', function() {
                let valorDigitado = $(this).val();
                
                // Permite apenas números, vírgula e ponto
                valorDigitado = valorDigitado.replace(/[^\d.,]/g, '');
                
                // Converte vírgula para ponto para cálculos
                valorDigitado = valorDigitado.replace(',', '.');
                
                // Permite apenas um ponto decimal
                const partes = valorDigitado.split('.');
                if (partes.length > 2) {
                    valorDigitado = partes[0] + '.' + partes.slice(1).join('');
                }
                
                // Limita a 4 casas decimais
                if (partes.length === 2 && partes[1].length > 4) {
                    valorDigitado = partes[0] + '.' + partes[1].substring(0, 4);
                }
                
                // Atualiza o valor no input apenas se mudou
                if ($(this).val() !== valorDigitado) {
                    $(this).val(valorDigitado);
                }
                
                let valor = parseFloat(valorDigitado) || 0;
                
                // Validação de casas decimais já feita no input acima
                
                // Não força valor mínimo durante digitação - apenas na confirmação
                
                const selectedOption = $materialSelect.find('option:selected');
                const valorUnitario = parseFloat(selectedOption.data('valor')) || 0;
                
                // Só recalcula automaticamente se há valor unitário
                if (valorUnitario > 0) {
                    const valorTotal = valor * valorUnitario;
                    
                    if (valorTotal > 0) {
                        const valorFormatado = valorTotal.toFixed(2).replace('.', ',');
                        $('#valorTotalInput').val('R$ ' + valorFormatado.replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
                    } else {
                        $('#valorTotalInput').val('');
                    }
                }
                // Se não há valor unitário, mantém o valor que o usuário digitou
            });
            
            $('#adicionarItemModal').css('display', 'flex');
            console.log('Modal exibido:', $('#adicionarItemModal').is(':visible'));
            console.log('Select2 container criado:', $('.select2-container').length);
            
            // Oculta o loading após carregar tudo
            $('#adicionarItemLoading').fadeOut(200);
        });
        
        // Eventos do modal de adicionar item
        $(document).off('click', '#adicionarItemCancel');
        $(document).on('click', '#adicionarItemCancel', function() {
            // Destroi o Select2 antes de fechar
            if ($('#materialSelect').hasClass('select2-hidden-accessible') && typeof $.fn.select2 !== 'undefined') {
                $('#materialSelect').select2('destroy');
            }
            
            // Limpa os campos
            $('#materialSelect').val('');
            $('#unInput').val('');
            $('#qtdInput').val('1');
            $('#valorTotalInput').val('');
            
            $('#adicionarItemModal').fadeOut(180);
        });
        
        $(document).off('click', '#adicionarItemConfirm');
        $(document).on('click', '#adicionarItemConfirm', async function() {
            const material = $('#materialSelect').val();
            const un = $('#unInput').val().trim();
            const qtd = $('#qtdInput').val();
            const valorTotal = $('#valorTotalInput').val().trim();
            
            if (!material) {
                showToast('Selecione um material!', 'error');
                return;
            }
            if (!un) {
                showToast('Informe a unidade!', 'error');
                return;
            }
            if (!qtd || qtd <= 0) {
                showToast('Informe uma quantidade válida (mínimo 0.0001)!', 'error');
                $('#qtdInput').focus();
                return;
            }
            if (parseFloat(qtd) < 0.0001) {
                showToast('A quantidade deve ser no mínimo 0.0001!', 'error');
                $('#qtdInput').focus();
                return;
            }
            
            // Valida se a quantidade tem no máximo 4 casas decimais
            const qtdStr = qtd.toString().replace(',', '.');
            if (qtdStr.includes('.') && qtdStr.split('.')[1].length > 4) {
                showToast('A quantidade deve ter no máximo 4 casas decimais!', 'error');
                $('#qtdInput').focus();
                return;
            }
            
            if (!valorTotal) {
                showToast('Informe o valor total!', 'error');
                return;
            }
            
            // Extrai o valor numérico do campo valor total (remove R$ e formatação)
            const valorNumerico = valorTotal.replace(/[^\d,]/g, '').replace(',', '.');
            if (isNaN(parseFloat(valorNumerico)) || parseFloat(valorNumerico) <= 0) {
                showToast('Informe um valor total válido!', 'error');
                return;
            }
            
            // Mostra loading
            $('#adicionarItemConfirm').prop('disabled', true).text('Salvando...');
            
            try {
                // Envia os dados para o servidor
                const formData = new FormData();
                formData.append('inserir_item', '1');
                formData.append('ss', ss);
                formData.append('material', material);
                formData.append('unidade', un);
                formData.append('quantidade', qtd);
                formData.append('valor_total', valorNumerico);
                
                const response = await fetch('server.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    
                    // Destroi o Select2 antes de fechar
                    if ($('#materialSelect').hasClass('select2-hidden-accessible') && typeof $.fn.select2 !== 'undefined') {
                        $('#materialSelect').select2('destroy');
                    }
                    
                    $('#adicionarItemModal').fadeOut(180);
                    
                    // Limpa os campos
                    $('#materialSelect').val('');
                    $('#unInput').val('');
                    $('#qtdInput').val('1');
                    $('#valorTotalInput').val('');
                    
                    // Recarrega a tabela de itens
                    $('#itensTable').DataTable().ajax.reload(function() {
                        // Callback após recarregar - o footerCallback será executado automaticamente
                    });
                    
                    // Recarrega a tabela principal para atualizar os contadores
                    $('#tasksTable').DataTable().ajax.reload();
                } else {
                    showToast(result.message || 'Erro ao adicionar item!', 'error');
                }
            } catch (error) {
                console.error('Erro ao salvar item:', error);
                showToast('Erro na comunicação com o servidor!', 'error');
            } finally {
                // Restaura o botão
                $('#adicionarItemConfirm').prop('disabled', false).text('Adicionar');
            }
        });
        
        // Remove eventos antigos
        $(document).off('click', '#incluirItensClose');
        
        // Fechar
        $(document).on('click', '#incluirItensClose', function() {
            // Desabilita o botão Adicionar Item quando fecha o modal
            $('#btnAdicionarItem').prop('disabled', true).text('Carregando...');
            $('#incluirItensModal').fadeOut(180);
        });
        
        // Evento para excluir item
        $(document).off('click', '.excluir-item');
        $(document).on('click', '.excluir-item', async function() {
            const itemId = $(this).data('id');
            
            if (!confirm('Tem certeza que deseja excluir este item?')) {
                return;
            }
            
            // Desabilita o botão durante a exclusão
            $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');
            
            try {
                const formData = new FormData();
                formData.append('excluir_item', '1');
                formData.append('item_id', itemId);
                
                const response = await fetch('server.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    // Recarrega a tabela de itens
                    $('#itensTable').DataTable().ajax.reload(function() {
                        // Callback após recarregar - o footerCallback será executado automaticamente
                    });
                    
                    // Recarrega a tabela principal para atualizar os contadores
                    $('#tasksTable').DataTable().ajax.reload();
                } else {
                    showToast(result.message || 'Erro ao excluir item!', 'error');
                    // Restaura o botão
                    $(this).prop('disabled', false).html('<i class="fa-solid fa-trash"></i>');
                }
            } catch (error) {
                console.error('Erro ao excluir item:', error);
                showToast('Erro na comunicação com o servidor!', 'error');
                // Restaura o botão
                $(this).prop('disabled', false).html('<i class="fa-solid fa-trash"></i>');
            }
        });
    });
});

// Função utilitária para slugificar textos para classe CSS
function slugify(text) {
    return text
        .toString()
        .normalize('NFD').replace(/[0-9]/g, "") // remove acentos
        .replace(/[^\w\s-]/g, '') // remove caracteres especiais
        .trim().toLowerCase()
        .replace(/\s+/g, '_');
}

// Função utilitária para mostrar toast
function showToast(msg, type = 'info') {
    Toastify({
        text: msg,
        duration: 3500,
        gravity: 'top',
        position: 'right',
        close: true,
        style: {
            background: type === 'success' ? '#1976d2' : (type === 'error' ? '#e53935' : '#2768ae'),
            color: '#fff',
            fontWeight: 500,
            fontSize: '15px',
            borderRadius: '8px',
            boxShadow: '0 2px 8px #1976d233',
            padding: '10px 18px'
        }
    }).showToast();
}
