/**
 * Automation Builder - Visual Flow Editor
 * Estilo Kommo/HubSpot - Fluxo vertical com nodes arrastaveis
 */

const AutomationBuilder = {
    automationId: window.AUTOMATION_ID || 0,
    flow: { nodes: [] },
    pipelines: [],
    stages: [],
    tags: [],
    users: [],
    templates: [],
    selectedNodeId: null,

    // Labels para tipos de nodes
    nodeLabels: {
        trigger: {
            lead_created: 'Lead criado',
            lead_stage_changed: 'Mudanca de etapa',
            whatsapp_message_received: 'Mensagem WhatsApp',
            tag_added: 'Tag adicionada',
        },
        condition: {
            stage_is: 'Etapa e',
            has_tag: 'Tem tag',
            field_equals: 'Campo igual a',
            message_contains: 'Mensagem contem',
        },
        action: {
            move_stage: 'Mover etapa',
            add_tag: 'Adicionar tag',
            remove_tag: 'Remover tag',
            send_whatsapp: 'Enviar WhatsApp',
            send_webhook: 'Enviar webhook',
            assign_user: 'Atribuir usuario',
        },
        delay: 'Esperar',
    },

    // Cores por tipo
    nodeColors: {
        trigger: { bg: 'bg-orange-50', border: 'border-orange-300', icon: 'text-orange-600' },
        condition: { bg: 'bg-indigo-50', border: 'border-indigo-300', icon: 'text-indigo-600' },
        action: { bg: 'bg-emerald-50', border: 'border-emerald-300', icon: 'text-emerald-600' },
        delay: { bg: 'bg-amber-50', border: 'border-amber-300', icon: 'text-amber-600' },
    },

    async init() {
        await this.loadDependencies();
        this.bindEvents();
        this.bindPaletteEvents();
        this.bindConfigPanelEvents();
        this.bindSaveEvents();

        if (this.automationId > 0) {
            await this.loadAutomation();
        } else {
            this.render();
        }
    },

    async loadDependencies() {
        try {
            const [pipelines, tags, users, templates] = await Promise.all([
                API.get('/api/pipelines').then(r => r.data?.pipelines || []),
                API.get('/api/tags').then(r => r.data?.tags || []),
                API.get('/api/users').then(r => r.data?.users || []),
                API.get('/api/templates').then(r => r.data?.templates || []),
            ]);

            this.pipelines = pipelines;
            // Extrair stages de todos os pipelines
            this.stages = [];
            if (Array.isArray(pipelines)) {
                pipelines.forEach(p => {
                    if (p.stages) {
                        p.stages.forEach(s => this.stages.push({ ...s, pipeline_name: p.name }));
                    }
                });
            }
            this.tags = tags;
            this.users = users;
            this.templates = templates;
        } catch (err) {
            console.error('Erro ao carregar dependencias:', err);
            App.toast('Erro ao carregar dados de suporte', 'error');
        }
    },

    async loadAutomation() {
        try {
            const res = await API.get(`/api/automations/${this.automationId}`);
            const rule = res.data?.rule;
            if (!rule) {
                App.toast('Automacao nao encontrada', 'error');
                return;
            }

            // Preencher formulario
            document.getElementById('flow-name').value = rule.name || '';
            document.getElementById('flow-description').value = rule.description || '';
            document.getElementById('flow-active').checked = !!rule.is_active;
            const flowActiveKnob = document.getElementById('flow-active-knob');
            if (flowActiveKnob) {
                flowActiveKnob.classList.toggle('translate-x-5', !!rule.is_active);
            }

            // Carregar flow ou criar a partir de dados legados
            if (rule.flow && rule.flow.nodes) {
                this.flow = rule.flow;
            } else if (rule.trigger_event) {
                // Converter dados legados para formato visual
                this.flow = this.convertLegacyToFlow(rule);
            } else {
                this.flow = { nodes: [] };
            }

            this.render();
        } catch (err) {
            console.error(err);
            App.toast('Erro ao carregar automacao', 'error');
        }
    },

    convertLegacyToFlow(rule) {
        const nodes = [];
        let y = 0;

        // Trigger node
        const triggerId = 'trigger_' + Date.now();
        nodes.push({
            id: triggerId,
            type: 'trigger',
            subtype: rule.trigger_event,
            config: rule.trigger_config || {},
            position: { x: 0, y: y },
            next: null,
        });
        y += 100;

        // Adicionar acoes legadas se houver
        // Nota: Acos legadas nao sao carregadas individualmente, apenas a estrutura basica

        return { nodes };
    },

    bindEvents() {
        // Botao adicionar entre nodes (dinamico)
        document.addEventListener('click', (e) => {
            if (e.target.matches('.add-node-btn') || e.target.closest('.add-node-btn')) {
                const btn = e.target.matches('.add-node-btn') ? e.target : e.target.closest('.add-node-btn');
                const afterId = btn.dataset.after;
                const branch = btn.dataset.branch || null; // 'yes', 'no', ou null
                this.showAddNodeMenu(afterId, branch);
            }
        });

        // Toggle switch do fluxo ativo
        const flowActive = document.getElementById('flow-active');
        const flowActiveKnob = document.getElementById('flow-active-knob');
        if (flowActive && flowActiveKnob) {
            flowActive.addEventListener('change', (e) => {
                flowActiveKnob.classList.toggle('translate-x-5', e.target.checked);
            });
        }
    },

    bindPaletteEvents() {
        document.querySelectorAll('.node-palette').forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.dataset.type;
                const subtype = btn.dataset.subtype;
                this.addNode(type, subtype);
            });
        });
    },

    bindConfigPanelEvents() {
        document.getElementById('config-close').addEventListener('click', () => this.closeConfigPanel());
        document.getElementById('config-overlay').addEventListener('click', () => this.closeConfigPanel());
        document.getElementById('config-save').addEventListener('click', () => this.saveNodeConfig());
    },

    bindSaveEvents() {
        document.getElementById('btn-save').addEventListener('click', () => this.saveAutomation());
    },

    addNode(type, subtype, afterId = null, branch = null) {
        // Verificar se ja existe trigger
        if (type === 'trigger') {
            const existingTrigger = this.flow.nodes.find(n => n.type === 'trigger');
            if (existingTrigger) {
                App.toast('Ja existe um gatilho. Apenas um por fluxo.', 'error');
                return;
            }
        }

        const node = {
            id: `${type}_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`,
            type,
            subtype,
            config: {},
            position: { x: 0, y: this.flow.nodes.length * 100 },
        };

        if (type === 'condition') {
            node.yes = null;
            node.no = null;
        } else {
            node.next = null;
        }

        // Inserir na posicao correta
        if (afterId) {
            const afterNode = this.flow.nodes.find(n => n.id === afterId);
            if (afterNode) {
                // Conectar o novo node
                if (afterNode.type === 'condition') {
                    // Se branch foi especificado (yes/no), usar ele
                    if (branch === 'yes') {
                        node.next = afterNode.yes;
                        afterNode.yes = node.id;
                    } else if (branch === 'no') {
                        node.next = afterNode.no;
                        afterNode.no = node.id;
                    } else {
                        // Por padrao conecta no "Sim" se estiver vazio
                        if (!afterNode.yes) {
                            afterNode.yes = node.id;
                        } else if (!afterNode.no) {
                            afterNode.no = node.id;
                        } else {
                            // Substituir o Sim
                            node.next = afterNode.yes;
                            afterNode.yes = node.id;
                        }
                    }
                } else {
                    node.next = afterNode.next;
                    afterNode.next = node.id;
                }
            }
        } else if (type === 'trigger') {
            // Trigger sempre primeiro
            this.flow.nodes.unshift(node);
        } else {
            // Adicionar ao final do fluxo principal
            const lastNode = this.findLastNodeInMainFlow();
            if (lastNode) {
                if (lastNode.type === 'condition') {
                    if (!lastNode.yes) lastNode.yes = node.id;
                    else if (!lastNode.no) lastNode.no = node.id;
                } else {
                    lastNode.next = node.id;
                }
            }
        }

        if (type !== 'trigger') {
            this.flow.nodes.push(node);
        }

        this.render();

        // Abrir config automaticamente
        this.openNodeConfig(node.id);
    },

    findLastNodeInMainFlow() {
        if (this.flow.nodes.length === 0) return null;

        // Encontrar o trigger
        let current = this.flow.nodes.find(n => n.type === 'trigger');
        if (!current) return this.flow.nodes[this.flow.nodes.length - 1];

        // Seguir o fluxo principal
        const visited = new Set();
        while (current && !visited.has(current.id)) {
            visited.add(current.id);
            let nextId = current.next;
            if (current.type === 'condition') {
                nextId = current.yes || current.no;
            }
            if (!nextId) break;
            current = this.flow.nodes.find(n => n.id === nextId);
        }

        return current;
    },

    deleteNode(nodeId) {
        const node = this.flow.nodes.find(n => n.id === nodeId);
        if (!node) return;

        // Guardar referencias antes de modificar
        const targetNext = node.type === 'condition' ? node.yes : node.next;

        // Reconectar: quem apontava para este node agora aponta para o proximo do node removido
        this.flow.nodes.forEach(n => {
            if (n.next === nodeId) {
                n.next = targetNext;
            }
            if (n.yes === nodeId) {
                n.yes = targetNext;
            }
            if (n.no === nodeId) {
                n.no = targetNext;
            }
        });

        // Remover o node
        this.flow.nodes = this.flow.nodes.filter(n => n.id !== nodeId);

        // Limpar selecao se o node deletado estava selecionado
        if (this.selectedNodeId === nodeId) {
            this.selectedNodeId = null;
            this.closeConfigPanel();
        }

        this.render();
    },

    showAddNodeMenu(afterId, branch = null) {
        // Por simplicidade, adiciona um action node padrao
        // No futuro pode mostrar um modal com opcoes
        // Passa o branch (yes/no) para conectar corretamente em condicoes
        this.addNode('action', 'send_whatsapp', afterId, branch);
    },

    openNodeConfig(nodeId) {
        const node = this.flow.nodes.find(n => n.id === nodeId);
        if (!node) return;

        this.selectedNodeId = nodeId;
        const panel = document.getElementById('config-panel');
        const overlay = document.getElementById('config-overlay');
        const title = document.getElementById('config-title');
        const content = document.getElementById('config-content');

        const label = this.getNodeLabel(node);
        title.textContent = `Configurar: ${label}`;

        // Gerar formulario
        content.innerHTML = this.generateConfigForm(node);

        // Mostrar
        panel.classList.remove('translate-x-full');
        overlay.classList.remove('hidden');
    },

    closeConfigPanel() {
        const panel = document.getElementById('config-panel');
        const overlay = document.getElementById('config-overlay');
        panel.classList.add('translate-x-full');
        overlay.classList.add('hidden');
        this.selectedNodeId = null;
    },

    generateConfigForm(node) {
        const type = node.type;
        const subtype = node.subtype;
        const config = node.config || {};

        let html = '';

        // Configuracoes especificas por tipo
        if (type === 'trigger') {
            if (subtype === 'lead_stage_changed') {
                html += this.formSelect('stage_id', 'Etapa especifica (opcional)', this.stages, config.stage_id, 'id', 'name', true);
            } else if (subtype === 'tag_added') {
                html += this.formSelect('tag_id', 'Tag especifica (opcional)', this.tags, config.tag_id, 'id', 'name', true);
            } else if (subtype === 'whatsapp_message_received') {
                html += this.formInput('keyword', 'Palavra-chave (opcional)', config.keyword, 'Ex: preco, orcamento');
            }
        } else if (type === 'condition') {
            if (subtype === 'stage_is') {
                html += this.formSelect('stage_id', 'Etapa', this.stages, config.stage_id, 'id', 'name');
            } else if (subtype === 'has_tag') {
                html += this.formSelect('tag_id', 'Tag', this.tags, config.tag_id, 'id', 'name');
            } else if (subtype === 'message_contains') {
                html += this.formInput('keyword', 'Palavra-chave', config.keyword, 'Ex: preco');
            } else if (subtype === 'field_equals') {
                html += this.formInput('field', 'Campo', config.field, 'Ex: email, phone, source');
                html += this.formInput('value', 'Valor', config.value, 'Valor esperado');
            }
        } else if (type === 'action') {
            if (subtype === 'move_stage') {
                html += this.formSelect('stage_id', 'Mover para etapa', this.stages, config.stage_id, 'id', 'name');
            } else if (subtype === 'add_tag' || subtype === 'remove_tag') {
                html += this.formSelect('tag_id', 'Tag', this.tags, config.tag_id, 'id', 'name');
            } else if (subtype === 'send_whatsapp') {
                html += this.formTextarea('message', 'Mensagem', config.message, 'Use {{nome}} para nome do lead');
                html += this.formSelect('template_id', 'Ou usar template (opcional)', this.templates, config.template_id, 'id', 'name', true);
            } else if (subtype === 'send_webhook') {
                html += this.formInput('url', 'URL do webhook', config.url, 'https://n8n.../webhook/...');
                html += this.formSelect('method', 'Metodo', [
                    { id: 'POST', name: 'POST' },
                    { id: 'GET', name: 'GET' },
                ], config.method || 'POST', 'id', 'name');
            } else if (subtype === 'assign_user') {
                html += this.formSelect('user_id', 'Responsavel', this.users, config.user_id, 'id', 'name');
            }
        } else if (type === 'delay') {
            html += this.formNumber('amount', 'Quantidade', config.amount || 1, 1, 999);
            html += this.formSelect('unit', 'Unidade', [
                { id: 'minutes', name: 'Minutos' },
                { id: 'hours', name: 'Horas' },
                { id: 'days', name: 'Dias' },
            ], config.unit || 'hours', 'id', 'name');
        }

        return html;
    },

    formInput(name, label, value, placeholder = '') {
        return `
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">${label}</label>
                <input type="text" name="${name}" value="${value || ''}" placeholder="${placeholder}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            </div>
        `;
    },

    formNumber(name, label, value, min, max) {
        return `
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">${label}</label>
                <input type="number" name="${name}" value="${value}" min="${min}" max="${max}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            </div>
        `;
    },

    formTextarea(name, label, value, placeholder = '') {
        return `
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">${label}</label>
                <textarea name="${name}" rows="4" placeholder="${placeholder}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">${value || ''}</textarea>
            </div>
        `;
    },

    formSelect(name, label, options, value, valueKey, labelKey, optional = false) {
        const optionalLabel = optional ? '<option value="">-- Qualquer --</option>' : '<option value="">Selecione...</option>';
        const optionsHtml = options.map(opt => {
            const optValue = opt[valueKey];
            const optLabel = opt[labelKey];
            const selected = optValue == value ? 'selected' : '';
            return `<option value="${optValue}" ${selected}>${optLabel}</option>`;
        }).join('');

        return `
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">${label}</label>
                <select name="${name}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    ${optionalLabel}
                    ${optionsHtml}
                </select>
            </div>
        `;
    },

    saveNodeConfig() {
        if (!this.selectedNodeId) return;

        const node = this.flow.nodes.find(n => n.id === this.selectedNodeId);
        if (!node) return;

        // Coletar valores do formulario
        const form = document.getElementById('config-content');
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            if (input.name) {
                let value = input.value;
                if (input.type === 'number') {
                    value = parseInt(value) || 0;
                }
                node.config[input.name] = value;
            }
        });

        this.render();
        this.closeConfigPanel();
        App.toast('Configuracao salva', 'success');
    },

    async saveAutomation() {
        const btn = document.getElementById('btn-save');
        btn.disabled = true;

        const name = document.getElementById('flow-name').value.trim();
        if (!name) {
            App.toast('Nome do fluxo obrigatorio', 'error');
            btn.disabled = false;
            return;
        }

        // Encontrar trigger
        const trigger = this.flow.nodes.find(n => n.type === 'trigger');
        if (!trigger) {
            App.toast('Adicione um gatilho ao fluxo', 'error');
            btn.disabled = false;
            return;
        }

        const data = {
            id: this.automationId || 0,
            name: name,
            description: document.getElementById('flow-description').value,
            trigger_event: trigger.subtype,
            is_active: document.getElementById('flow-active').checked,
            priority: 0,
            flow: this.flow,
        };

        try {
            const res = await API.post('/api/automations', data);
            App.toast('Fluxo salvo com sucesso', 'success');

            // Se era novo, redirecionar para edicao
            if (!this.automationId && res.data?.id) {
                window.location.href = `/settings/automations/builder/${res.data.id}`;
            }
        } catch (err) {
            console.error(err);
            App.toast('Erro ao salvar fluxo', 'error');
        } finally {
            btn.disabled = false;
        }
    },

    render() {
        const container = document.getElementById('nodes-container');
        const emptyState = document.getElementById('empty-canvas');
        const svg = document.getElementById('connections-layer');

        // Mostrar/esconder estado vazio
        if (this.flow.nodes.length === 0) {
            emptyState.classList.remove('hidden');
            container.innerHTML = '';
            if (svg) svg.innerHTML = '';
            return;
        }
        emptyState.classList.add('hidden');

        // Renderizar nodes
        const trigger = this.flow.nodes.find(n => n.type === 'trigger');
        let html = '';

        if (trigger) {
            html += this.renderNode(trigger);
            html += this.renderConnections(trigger);
        }

        container.innerHTML = html;

        // Bind de eventos nos nodes
        this.bindNodeEvents();

        // Desenhar conexoes SVG (com delay para garantir que os elementos estao renderizados)
        setTimeout(() => this.drawConnections(), 50);

        // Redesenhar ao redimensionar
        window.addEventListener('resize', () => this.drawConnections());
    },

    renderNode(node, isBranch = false, branchLabel = null) {
        const colors = this.nodeColors[node.type] || this.nodeColors.action;
        const label = this.getNodeLabel(node);
        const subtitle = this.getNodeSubtitle(node);

        let html = '';

        // Wrapper para branch
        if (branchLabel) {
            html += `<div class="flex flex-col items-center gap-2">`;
            html += `<span class="text-xs font-medium text-slate-500">${branchLabel}</span>`;
        }

        // Botao adicionar antes (se nao for trigger)
        if (node.type !== 'trigger') {
            html += this.renderAddButton(node.id, 'before');
        }

        // Card do node (com drag handle)
        html += `
            <div class="node-card relative w-72 rounded-xl border-2 ${colors.border} ${colors.bg} p-4 shadow-sm cursor-pointer"
                 data-node-id="${node.id}" data-type="${node.type}" draggable="true">

                <!-- Cabecalho com handle de drag -->
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-lg bg-white ${colors.icon} flex items-center justify-center shadow-sm drag-handle cursor-grab" title="Arrastar para reordenar">
                            ${this.getNodeIcon(node.type)}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">${label}</p>
                            <p class="text-xs text-slate-600 truncate">${subtitle}</p>
                        </div>
                    </div>
                    <button type="button" class="delete-node-btn rounded p-1 text-slate-400 hover:text-red-600 hover:bg-red-50" data-id="${node.id}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        `;

        // Botao adicionar depois
        html += this.renderAddButton(node.id, 'after');

        // Conexoes especiais para condition
        if (node.type === 'condition') {
            html += `<div class="flex gap-8 mt-2">`;

            // Branch Sim
            html += `<div class="flex flex-col items-center gap-2">`;
            html += `<span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">Sim</span>`;
            if (node.yes) {
                const yesNode = this.flow.nodes.find(n => n.id === node.yes);
                if (yesNode) {
                    html += this.renderNode(yesNode, true);
                }
            } else {
                html += this.renderAddButton(node.id, 'yes');
            }
            html += `</div>`;

            // Branch Nao
            html += `<div class="flex flex-col items-center gap-2">`;
            html += `<span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded">Nao</span>`;
            if (node.no) {
                const noNode = this.flow.nodes.find(n => n.id === node.no);
                if (noNode) {
                    html += this.renderNode(noNode, true);
                }
            } else {
                html += this.renderAddButton(node.id, 'no');
            }
            html += `</div>`;

            html += `</div>`;
        } else if (node.next) {
            const nextNode = this.flow.nodes.find(n => n.id === node.next);
            if (nextNode) {
                html += this.renderNode(nextNode, isBranch);
            }
        }

        if (branchLabel) {
            html += `</div>`;
        }

        return html;
    },

    renderConnections(startNode) {
        // Esta funcao agora eh auxiliar para renderizar a partir de um node
        // A logica principal esta em renderNode que segue os next/yes/no
        return '';
    },

    renderAddButton(nodeId, position) {
        const afterAttr = position === 'after' ? `data-after="${nodeId}"` :
                          position === 'yes' ? `data-after="${nodeId}" data-branch="yes"` :
                          position === 'no' ? `data-after="${nodeId}" data-branch="no"` :
                          `data-before="${nodeId}"`;

        const isBranch = position === 'yes' || position === 'no';
        const branchColor = position === 'yes' ? 'hover:bg-emerald-500' : position === 'no' ? 'hover:bg-red-500' : 'hover:bg-primary-500';

        return `
            <div class="flex flex-col items-center gap-1 my-2">
                <button type="button" class="add-node-btn group flex items-center justify-center w-8 h-8 rounded-full bg-white border-2 border-slate-300 ${branchColor} hover:border-transparent hover:text-white transition-all shadow-sm"
                    ${afterAttr} title="Adicionar node">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
                ${isBranch ? `<span class="text-[10px] font-medium text-slate-400 uppercase">${position}</span>` : ''}
            </div>
        `;
    },

    drawConnections() {
        // Desenha as conexoes SVG entre os nodes
        const svg = document.getElementById('connections-layer');
        const canvas = document.getElementById('flow-canvas');
        if (!svg || !canvas) return;

        // Limpar conexoes anteriores (mantendo defs)
        const defs = svg.querySelector('defs');
        svg.innerHTML = '';
        if (defs) svg.appendChild(defs);

        // Desenhar conexoes recursivamente a partir do trigger
        const trigger = this.flow.nodes.find(n => n.type === 'trigger');
        if (trigger) {
            this.drawNodeConnections(trigger, svg);
        }
    },

    drawNodeConnections(node, svg) {
        const fromEl = document.querySelector(`.node-card[data-node-id="${node.id}"]`);
        if (!fromEl) return;

        const fromRect = fromEl.getBoundingClientRect();
        const svgRect = svg.getBoundingClientRect();

        // Conexao next (para baixo)
        if (node.next && node.type !== 'condition') {
            const toEl = document.querySelector(`.node-card[data-node-id="${node.next}"]`);
            if (toEl) {
                this.drawConnectionLine(svg, fromEl, toEl, 'default', svgRect);
                const nextNode = this.flow.nodes.find(n => n.id === node.next);
                if (nextNode) this.drawNodeConnections(nextNode, svg);
            }
        }

        // Conexoes yes/no para condition
        if (node.type === 'condition') {
            if (node.yes) {
                const toEl = document.querySelector(`.node-card[data-node-id="${node.yes}"]`);
                if (toEl) {
                    this.drawConnectionLine(svg, fromEl, toEl, 'yes', svgRect);
                    const yesNode = this.flow.nodes.find(n => n.id === node.yes);
                    if (yesNode) this.drawNodeConnections(yesNode, svg);
                }
            }
            if (node.no) {
                const toEl = document.querySelector(`.node-card[data-node-id="${node.no}"]`);
                if (toEl) {
                    this.drawConnectionLine(svg, fromEl, toEl, 'no', svgRect);
                    const noNode = this.flow.nodes.find(n => n.id === node.no);
                    if (noNode) this.drawNodeConnections(noNode, svg);
                }
            }
        }
    },

    drawConnectionLine(svg, fromEl, toEl, type, svgRect) {
        const fromRect = fromEl.getBoundingClientRect();
        const toRect = toEl.getBoundingClientRect();

        // Calcular pontos (centro inferior do from -> centro superior do to)
        const x1 = fromRect.left + fromRect.width / 2 - svgRect.left;
        const y1 = fromRect.bottom - svgRect.top - 5;
        const x2 = toRect.left + toRect.width / 2 - svgRect.left;
        const y2 = toRect.top - svgRect.top + 5;

        // Criar path curvo
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const controlY = Math.abs(y2 - y1) / 2;
        const d = `M ${x1} ${y1} C ${x1} ${y1 + controlY}, ${x2} ${y2 - controlY}, ${x2} ${y2}`;

        let strokeColor = '#64748b'; // default slate-500
        let markerEnd = 'url(#arrowhead)';

        if (type === 'yes') {
            strokeColor = '#10b981'; // emerald-500
            markerEnd = 'url(#arrowhead-yes)';
        } else if (type === 'no') {
            strokeColor = '#ef4444'; // red-500
            markerEnd = 'url(#arrowhead-no)';
        }

        path.setAttribute('d', d);
        path.setAttribute('stroke', strokeColor);
        path.setAttribute('stroke-width', '2');
        path.setAttribute('fill', 'none');
        path.setAttribute('marker-end', markerEnd);
        path.setAttribute('class', 'connection-line');

        svg.appendChild(path);
    },

    bindNodeEvents() {
        // Click para editar
        document.querySelectorAll('.node-card').forEach(card => {
            card.addEventListener('click', (e) => {
                // Nao abrir se clicou no botao de delete
                if (e.target.closest('.delete-node-btn')) return;

                const nodeId = card.dataset.nodeId;
                this.openNodeConfig(nodeId);
            });
        });

        // Delete
        document.querySelectorAll('.delete-node-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                const ok = await App.confirmDialog({
                    title: 'Excluir step',
                    message: 'Deseja realmente excluir este step do fluxo?',
                    confirmText: 'Excluir',
                    danger: true
                });
                if (ok) {
                    this.deleteNode(id);
                }
            });
        });

        // Add buttons
        document.querySelectorAll('.add-node-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                // Mostrar menu de adicao - por enquanto adiciona action padrao
                this.showAddNodeMenu(btn.dataset.after);
            });
        });

        // Drag and Drop
        this.bindDragAndDrop();
    },

    bindDragAndDrop() {
        let draggedNodeId = null;
        let draggedElement = null;

        document.querySelectorAll('.node-card').forEach(card => {
            // Drag start
            card.draggable = true;

            card.addEventListener('dragstart', (e) => {
                draggedNodeId = card.dataset.nodeId;
                draggedElement = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggedNodeId);
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                draggedNodeId = null;
                draggedElement = null;

                // Limpar indicadores
                document.querySelectorAll('.drop-indicator').forEach(el => el.remove());
                document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            });

            // Drag over
            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (draggedNodeId && draggedNodeId !== card.dataset.nodeId) {
                    card.classList.add('drag-over');
                }
            });

            // Drag leave
            card.addEventListener('dragleave', () => {
                card.classList.remove('drag-over');
            });

            // Drop
            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.classList.remove('drag-over');

                const droppedNodeId = e.dataTransfer.getData('text/plain');
                const targetNodeId = card.dataset.nodeId;

                if (droppedNodeId && droppedNodeId !== targetNodeId) {
                    this.moveNode(droppedNodeId, targetNodeId);
                }
            });
        });

        // Permitir drop no container vazio
        const container = document.getElementById('nodes-container');
        if (container) {
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
            });

            container.addEventListener('drop', (e) => {
                e.preventDefault();
                const droppedNodeId = e.dataTransfer.getData('text/plain');
                if (droppedNodeId && e.target === container) {
                    // Drop no final
                    this.moveNodeToEnd(droppedNodeId);
                }
            });
        }
    },

    moveNode(draggedId, targetId) {
        // Reordenar nodes no fluxo
        const draggedNode = this.flow.nodes.find(n => n.id === draggedId);
        const targetNode = this.flow.nodes.find(n => n.id === targetId);

        if (!draggedNode || !targetNode) return;

        // Se o target eh o proximo do dragged, nao faz nada
        if (draggedNode.next === targetId) return;

        // Remover o dragged da posicao atual (desconectar de quem apontava para ele)
        this.flow.nodes.forEach(n => {
            if (n.next === draggedId) n.next = null;
            if (n.yes === draggedId) n.yes = null;
            if (n.no === draggedId) n.no = null;
        });

        // Inserir antes do target
        this.flow.nodes.forEach(n => {
            if (n.next === targetId) n.next = draggedId;
            if (n.yes === targetId) n.yes = draggedId;
            if (n.no === targetId) n.no = draggedId;
        });

        // Conectar dragged ao target
        draggedNode.next = targetId;

        this.render();
        App.toast('Node movido', 'success');
    },

    moveNodeToEnd(nodeId) {
        const node = this.flow.nodes.find(n => n.id === nodeId);
        if (!node) return;

        // Remover das conexoes atuais
        this.flow.nodes.forEach(n => {
            if (n.next === nodeId) n.next = null;
            if (n.yes === nodeId) n.yes = null;
            if (n.no === nodeId) n.no = null;
        });

        // Encontrar o ultimo node e conectar
        let lastNode = this.flow.nodes.find(n => n.type === 'trigger');
        while (lastNode && lastNode.next) {
            const next = this.flow.nodes.find(n => n.id === lastNode.next);
            if (next) lastNode = next;
            else break;
        }

        if (lastNode && lastNode.id !== nodeId) {
            lastNode.next = nodeId;
            node.next = null;
        }

        this.render();
        App.toast('Node movido para o final', 'success');
    },

    getNodeLabel(node) {
        if (node.type === 'delay') {
            return this.nodeLabels.delay;
        }
        const labels = this.nodeLabels[node.type];
        if (!labels) return 'Node';
        if (typeof labels === 'string') return labels;
        return labels[node.subtype] || node.subtype || 'Node';
    },

    getNodeSubtitle(node) {
        const config = node.config || {};

        if (node.type === 'trigger') {
            return '';
        } else if (node.type === 'condition') {
            if (node.subtype === 'stage_is' && config.stage_id) {
                const stage = this.stages.find(s => s.id == config.stage_id);
                return stage ? stage.name : `Etapa ${config.stage_id}`;
            } else if (node.subtype === 'has_tag' && config.tag_id) {
                const tag = this.tags.find(t => t.id == config.tag_id);
                return tag ? tag.name : `Tag ${config.tag_id}`;
            } else if (node.subtype === 'message_contains') {
                return config.keyword ? `"${config.keyword}"` : 'Qualquer mensagem';
            }
        } else if (node.type === 'action') {
            if (node.subtype === 'move_stage' && config.stage_id) {
                const stage = this.stages.find(s => s.id == config.stage_id);
                return stage ? `-> ${stage.name}` : `Etapa ${config.stage_id}`;
            } else if ((node.subtype === 'add_tag' || node.subtype === 'remove_tag') && config.tag_id) {
                const tag = this.tags.find(t => t.id == config.tag_id);
                return tag ? tag.name : `Tag ${config.tag_id}`;
            } else if (node.subtype === 'send_whatsapp') {
                if (config.template_id) {
                    const tpl = this.templates.find(t => t.id == config.template_id);
                    return tpl ? `Template: ${tpl.name}` : 'Mensagem (template)';
                }
                return config.message ? config.message.substring(0, 30) + '...' : 'Mensagem';
            } else if (node.subtype === 'send_webhook') {
                return config.url ? config.url.substring(0, 30) + '...' : 'Webhook';
            } else if (node.subtype === 'assign_user' && config.user_id) {
                const user = this.users.find(u => u.id == config.user_id);
                return user ? user.name : `Usuario ${config.user_id}`;
            }
        } else if (node.type === 'delay') {
            const amount = config.amount || 1;
            const unit = config.unit || 'hours';
            const unitLabel = unit === 'minutes' ? 'min' : unit === 'hours' ? 'h' : 'dias';
            return `${amount} ${unitLabel}`;
        }

        return 'Clique para configurar';
    },

    getNodeIcon(type) {
        const icons = {
            trigger: `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>`,
            condition: `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
            action: `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>`,
            delay: `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
        };
        return icons[type] || icons.action;
    },
};

// Inicializar quando DOM pronto
document.addEventListener('DOMContentLoaded', () => {
    AutomationBuilder.init();
});
