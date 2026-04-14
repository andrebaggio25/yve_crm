/**
 * Yve CRM - Kanban JavaScript
 */

const Kanban = {
    pipelineId: null,
    columns: [],
    isLoading: false,
    _dragLeadId: null,
    _lastDragEndAt: 0,

    init() {
        const page = document.querySelector('.kanban-page');
        this.pipelineId = page?.dataset.pipelineId ? parseInt(page.dataset.pipelineId, 10) : 1;
        this.bindEvents();
        this.initDragAndDrop();
        this.initLeadDetailKeyboard();
        this.loadUsers();
        this.loadTags();
        this.loadData();
        this.initLeadModal();
    },

    bindEvents() {
        document.getElementById('btn-refresh')?.addEventListener('click', () => this.loadData());

        const searchInput = document.getElementById('search-leads');
        if (searchInput) {
            searchInput.addEventListener('input', App.debounce(() => this.renderBoard(), 300));
        }

        const userFilter = document.getElementById('filter-user');
        if (userFilter) {
            userFilter.addEventListener('change', () => this.renderBoard());
        }

        const tagFilter = document.getElementById('filter-tag');
        if (tagFilter) {
            tagFilter.addEventListener('change', () => this.loadData());
        }

        document.getElementById('lead-detail-backdrop')?.addEventListener('click', () => this.closeLeadDetail());
        document.getElementById('lead-detail-close')?.addEventListener('click', () => this.closeLeadDetail());

        document.getElementById('btn-add-lead')?.addEventListener('click', () => this.openLeadModal());
    },

    initLeadDetailKeyboard() {
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const modal = document.getElementById('lead-detail-modal');
            if (modal && !modal.classList.contains('hidden')) this.closeLeadDetail();
        });
    },

    initDragAndDrop() {
        const root = document.querySelector('.kanban-page');
        if (!root || root.dataset.kanbanDndBound === '1') return;
        root.dataset.kanbanDndBound = '1';

        root.addEventListener('dragstart', (e) => {
            const card = e.target.closest('.kanban-card');
            if (!card) return;
            const id = card.getAttribute('data-lead-id');
            if (!id) return;
            e.dataTransfer.setData('text/plain', id);
            e.dataTransfer.setData('application/x-kanban-stage', card.getAttribute('data-stage-id') || '');
            e.dataTransfer.effectAllowed = 'move';
            this._dragLeadId = id;
            card.classList.add('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary-400');
        });

        root.addEventListener('dragend', (e) => {
            const card = e.target.closest('.kanban-card');
            if (card) card.classList.remove('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary-400');
            this._lastDragEndAt = Date.now();
            this._dragLeadId = null;
            root.querySelectorAll('[data-kanban-column].kanban-dnd-active').forEach((el) => this.clearColumnDropStyle(el));
        });

        root.addEventListener('dragover', (e) => {
            if (!this._dragLeadId) return;
            const col = e.target.closest('[data-kanban-column]');
            if (!col) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            root.querySelectorAll('[data-kanban-column].kanban-dnd-active').forEach((el) => {
                if (el !== col) this.clearColumnDropStyle(el);
            });
            col.classList.add('kanban-dnd-active', 'ring-2', 'ring-primary-400', 'ring-offset-2', 'bg-primary-50/60');
        });

        root.addEventListener('dragleave', (e) => {
            const col = e.target.closest('[data-kanban-column]');
            if (!col) return;
            const related = e.relatedTarget;
            if (related && col.contains(related)) return;
            this.clearColumnDropStyle(col);
        });

        root.addEventListener('drop', async (e) => {
            const col = e.target.closest('[data-kanban-column]');
            if (!col || !this._dragLeadId) return;
            e.preventDefault();
            this.clearColumnDropStyle(col);
            const stageId = col.getAttribute('data-stage-id');
            const fromStage = e.dataTransfer.getData('application/x-kanban-stage');
            const leadId = parseInt(this._dragLeadId, 10);
            if (!stageId || !leadId) return;
            if (fromStage && String(fromStage) === String(stageId)) {
                return;
            }
            try {
                const res = await API.leads.moveStage(leadId, parseInt(stageId, 10));
                if (res.success) {
                    App.toast('Lead movido', 'success');
                    this.loadData();
                } else {
                    App.toast(res.message || 'Nao foi possivel mover o lead', 'error');
                }
            } catch (err) {
                App.toast(err.message || 'Erro ao mover lead', 'error');
            }
        });

        root.addEventListener('click', (e) => {
            const card = e.target.closest('.kanban-card[data-lead-id]');
            if (!card || e.target.closest('button')) return;
            if (Date.now() - this._lastDragEndAt < 400) return;
            const id = card.getAttribute('data-lead-id');
            if (id) this.openLeadDetail(parseInt(id, 10));
        });
    },

    clearColumnDropStyle(el) {
        el.classList.remove('kanban-dnd-active', 'ring-2', 'ring-primary-400', 'ring-offset-2', 'bg-primary-50/60');
    },

    initLeadModal() {
        const modal = document.getElementById('lead-create-modal');
        const form = document.getElementById('lead-create-form');
        const close = () => {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        };

        document.getElementById('lead-modal-close')?.addEventListener('click', close);
        document.getElementById('lead-modal-cancel')?.addEventListener('click', close);
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('lead-modal-save');
            const data = {
                name: document.getElementById('lead-name').value.trim(),
                phone: document.getElementById('lead-phone').value.trim() || undefined,
                email: document.getElementById('lead-email').value.trim() || undefined,
                pipeline_id: parseInt(document.getElementById('lead-pipeline').value, 10),
            };
            if (!data.name || !data.pipeline_id) {
                App.toast('Preencha nome e pipeline', 'warning');
                return;
            }
            btn.disabled = true;
            try {
                const res = await API.leads.create(data);
                if (res.success) {
                    App.toast('Lead criado com sucesso', 'success');
                    close();
                    form.reset();
                    this.loadData();
                } else {
                    App.toast(res.message || 'Erro ao criar lead', 'error');
                }
            } catch (err) {
                console.error(err);
                App.toast('Erro ao criar lead', 'error');
            } finally {
                btn.disabled = false;
            }
        });

        this.loadPipelinesForModal();
    },

    async loadPipelinesForModal() {
        const sel = document.getElementById('lead-pipeline');
        if (!sel) return;
        try {
            const res = await API.kanban.listPipelines();
            sel.innerHTML = '';
            if (res.success && res.data?.pipelines?.length) {
                res.data.pipelines.forEach((p) => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    if (String(p.id) === String(this.pipelineId)) opt.selected = true;
                    sel.appendChild(opt);
                });
            } else {
                const opt = document.createElement('option');
                opt.value = this.pipelineId;
                opt.textContent = 'Pipeline atual';
                sel.appendChild(opt);
            }
        } catch (e) {
            console.error(e);
            const opt = document.createElement('option');
            opt.value = this.pipelineId;
            opt.textContent = 'Pipeline atual';
            sel.appendChild(opt);
        }
    },

    openLeadModal() {
        const modal = document.getElementById('lead-create-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        this.loadPipelinesForModal();
    },

    async loadUsers() {
        try {
            const response = await API.users.list();
            if (response.success) {
                const select = document.getElementById('filter-user');
                if (select) {
                    response.data.users.forEach((user) => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.name;
                        select.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Erro ao carregar usuarios:', error);
        }
    },

    async loadTags() {
        try {
            const response = await API.tags.list();
            if (response.success && response.data?.tags) {
                const select = document.getElementById('filter-tag');
                if (select) {
                    response.data.tags.forEach((t) => {
                        const option = document.createElement('option');
                        option.value = t.id;
                        option.textContent = t.name;
                        select.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Erro ao carregar tags:', error);
        }
    },

    async loadData() {
        if (this.isLoading) return;

        this.isLoading = true;
        const board = document.getElementById('kanban-board');
        board.innerHTML = '<div class="flex flex-1 flex-col items-center justify-center gap-2 py-12 text-slate-500"><div class="spinner h-10 w-10 rounded-full border-2 border-slate-200 border-t-primary-600"></div><p class="text-sm">Carregando leads...</p></div>';

        const tagId = document.getElementById('filter-tag')?.value || '';

        try {
            const params = { limit: 2500 };
            if (tagId) params.tag_id = tagId;
            const response = await API.kanban.getData(this.pipelineId, params);

            if (response.success) {
                this.columns = response.data.columns;
                this.renderBoard();
            } else {
                board.innerHTML = '<div class="flex flex-1 items-center justify-center p-8 text-slate-500">Erro ao carregar dados</div>';
            }
        } catch (error) {
            console.error('Erro:', error);
            board.innerHTML = '<div class="flex flex-1 items-center justify-center p-8 text-slate-500">Erro ao carregar dados</div>';
        } finally {
            this.isLoading = false;
        }
    },

    renderBoard() {
        const board = document.getElementById('kanban-board');
        if (!board || !this.columns?.length) return;

        board.innerHTML = '';
        board.className = 'kanban-board flex min-h-0 flex-1 gap-4 overflow-x-auto overflow-y-hidden bg-slate-50 px-4 py-4 sm:px-6';

        const search = document.getElementById('search-leads')?.value || '';
        const userId = document.getElementById('filter-user')?.value || '';
        const clientFilter = !!(search || userId);

        this.columns.forEach((column) => {
            let leads = column.leads;

            if (search) {
                const searchLower = search.toLowerCase();
                leads = leads.filter(
                    (l) =>
                        (l.name && l.name.toLowerCase().includes(searchLower)) ||
                        (l.phone && l.phone.includes(search)) ||
                        (l.email && l.email.toLowerCase().includes(searchLower)) ||
                        (l.source && l.source.toLowerCase().includes(searchLower)) ||
                        (l.product_interest && l.product_interest.toLowerCase().includes(searchLower))
                );
            }

            if (userId) {
                leads = leads.filter((l) => String(l.assigned_user_id) === String(userId));
            }

            const totalInStage = column.total_in_stage != null ? column.total_in_stage : leads.length;
            const sumVisible = leads.reduce((s, l) => s + (parseFloat(String(l.value)) || 0), 0);
            const colEl = this.createColumn(column.stage, leads, sumVisible, {
                totalInStage,
                clientFilter,
            });
            board.appendChild(colEl);
        });
    },

    createColumn(stage, leads, totalValue, meta = {}) {
        const column = document.createElement('div');
        column.className =
            'kanban-column flex w-[300px] shrink-0 flex-col rounded-xl bg-slate-100 max-h-full transition-[box-shadow] duration-150 sm:w-[320px]';
        column.setAttribute('data-kanban-column', '1');
        column.setAttribute('data-stage-id', String(stage.id));

        const totalInStage = meta.totalInStage ?? leads.length;
        const badge = meta.clientFilter ? `${leads.length} visiveis` : `${totalInStage}`;

        const cardsHtml = leads.map((lead) => this.renderLeadCard(lead, stage.id)).join('');

        column.innerHTML = `
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200/80 px-3 py-3">
                <div class="flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-800">
                    <span class="h-3 w-3 shrink-0 rounded" style="background:${stage.color_token || '#94a3b8'}"></span>
                    <span class="truncate">${this.escapeHtml(stage.name)}</span>
                </div>
                <span class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600" title="Leads nesta etapa">${badge}</span>
            </div>
            <div class="border-b border-slate-200/80 px-3 py-2 text-xs text-slate-500">Soma (visivel): ${this.formatCurrency(totalValue)}</div>
            <div class="kanban-column-scroll min-h-0 flex-1 space-y-2 overflow-y-auto p-3">${cardsHtml || '<p class="text-center text-xs text-slate-400">Vazio</p>'}</div>
        `;

        column.querySelectorAll('.kanban-wa-btn').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const leadId = btn.getAttribute('data-lead-id');
                if (!leadId) return;
                await this.runWhatsAppTrigger(parseInt(leadId, 10), {});
            });
        });

        return column;
    },

    renderLeadCard(lead, stageId) {
        const isOverdue = lead.next_action_at && new Date(lead.next_action_at) < new Date();
        const cardClasses = [
            'kanban-card rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:border-primary-300 hover:shadow-md',
            'cursor-grab active:cursor-grabbing select-none',
        ];
        if (isOverdue) cardClasses.push('border-l-4 border-l-red-500');
        if (lead.temperature === 'hot') cardClasses.push('border-l-4 border-l-amber-500');

        const hasWa = !!(lead.phone_normalized || (lead.phone && String(lead.phone).replace(/\D/g, '').length >= 8));

        const tags = lead.tag_labels
            ? lead.tag_labels
                  .split('||')
                  .filter(Boolean)
                  .slice(0, 4)
            : [];
        const tagExtra =
            (lead.tags_count || 0) > tags.length
                ? `+${(lead.tags_count || 0) - tags.length}`
                : !tags.length && (lead.tags_count || 0) > 0
                  ? `${lead.tags_count} tags`
                  : '';

        const phoneLine = lead.phone
            ? `<div class="truncate font-mono text-xs text-slate-600">${this.escapeHtml(lead.phone)}</div>`
            : '';
        const emailLine =
            lead.email && lead.email.length
                ? `<div class="truncate text-xs text-slate-500">${this.escapeHtml(lead.email)}</div>`
                : '';
        const sourceLine = lead.source
            ? `<div class="truncate text-[11px] text-slate-400">Origem: ${this.escapeHtml(lead.source)}</div>`
            : '';
        const productLine = lead.product_interest
            ? `<div class="mt-1 line-clamp-2 text-[11px] leading-snug text-slate-600" title="${this.escapeHtml(lead.product_interest)}">Prod.: ${this.escapeHtml(lead.product_interest)}</div>`
            : '';

        const tagPills = tags
            .map(
                (t) =>
                    `<span class="inline-block max-w-[7rem] truncate rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">${this.escapeHtml(t)}</span>`
            )
            .join(' ');
        const tagRow =
            tagPills || tagExtra
                ? `<div class="mt-1.5 flex flex-wrap items-center gap-1">${tagPills}${
                      tagExtra ? `<span class="text-[10px] text-slate-400">${tagExtra}</span>` : ''
                  }</div>`
                : '';

        return `
            <div class="${cardClasses.join(' ')}" data-lead-id="${lead.id}" data-stage-id="${stageId}" draggable="true">
                <div class="text-sm font-semibold leading-snug text-slate-900">${this.escapeHtml(lead.name)}</div>
                ${phoneLine}
                ${emailLine}
                ${sourceLine}
                ${productLine}
                ${tagRow}
                <div class="mt-2 text-sm font-medium text-slate-700">${this.formatCurrency(lead.value)}</div>
                <div class="mt-2 flex items-center justify-between gap-2 border-t border-slate-100 pt-2">
                    <div class="flex min-w-0 items-center gap-2 text-xs text-slate-500">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700">${(lead.assigned_user_name || 'U').charAt(0).toUpperCase()}</span>
                        <span class="truncate">${this.escapeHtml(lead.assigned_user_name || 'Nao atribuido')}</span>
                    </div>
                    ${
                        hasWa
                            ? `<button type="button" class="kanban-wa-btn shrink-0 rounded p-1.5 text-emerald-600 hover:bg-emerald-50" data-lead-id="${lead.id}" title="WhatsApp (template + historico)">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.35-8.413"/></svg>
                    </button>`
                            : ''
                    }
                </div>
            </div>
        `;
    },

    resetLeadDetailToolbar() {
        const toolbar = document.getElementById('lead-detail-toolbar');
        toolbar?.classList.add('hidden');
        ['lead-detail-act-wa', 'lead-detail-act-mail', 'lead-detail-act-tel', 'lead-detail-act-copy-phone'].forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.add('hidden');
            if (el.tagName === 'A') {
                el.setAttribute('href', '#');
            }
            if (id === 'lead-detail-act-wa') {
                el.onclick = null;
            }
        });
    },

    async runWhatsAppTrigger(leadId, payload = {}) {
        try {
            const res = await API.leads.triggerWhatsApp(leadId, payload);
            if (res.success && res.data?.whatsapp_link) {
                window.open(res.data.whatsapp_link, '_blank', 'noopener,noreferrer');
                App.toast(res.message || 'WhatsApp aberto', 'success');
                return res;
            }
            App.toast(res.message || 'Nao foi possivel abrir o WhatsApp', 'error');
            return res;
        } catch (err) {
            console.error(err);
            App.toast('Erro ao contactar o servidor', 'error');
            return null;
        }
    },

    setupLeadDetailToolbar(lead) {
        this.resetLeadDetailToolbar();
        const toolbar = document.getElementById('lead-detail-toolbar');
        const wa = document.getElementById('lead-detail-act-wa');
        const mail = document.getElementById('lead-detail-act-mail');
        const tel = document.getElementById('lead-detail-act-tel');
        const copy = document.getElementById('lead-detail-act-copy-phone');
        if (!toolbar) return;

        const phoneRaw = lead.phone != null ? String(lead.phone).trim() : '';
        const digits = phoneRaw.replace(/\D/g, '');
        const hasPhone = digits.length >= 8;
        const emailStr = lead.email != null ? String(lead.email).trim() : '';
        const hasEmail = emailStr.length > 0;

        if (hasPhone) {
            wa?.classList.remove('hidden');
            wa.onclick = async () => {
                const modal = document.getElementById('lead-detail-modal');
                const lid = modal?.dataset?.leadId ? parseInt(modal.dataset.leadId, 10) : null;
                if (lid) await this.runWhatsAppTrigger(lid, {});
            };
            tel?.classList.remove('hidden');
            tel.href = `tel:${digits}`;
            copy?.classList.remove('hidden');
            copy.onclick = () => {
                navigator.clipboard.writeText(phoneRaw).then(
                    () => App.toast('Telefone copiado', 'success'),
                    () => App.toast('Nao foi possivel copiar', 'error')
                );
            };
        }

        if (hasEmail) {
            mail?.classList.remove('hidden');
            mail.href = `mailto:${emailStr}`;
        }

        if (hasPhone || hasEmail) {
            toolbar.classList.remove('hidden');
        }
    },

    async openLeadDetail(leadId) {
        const modal = document.getElementById('lead-detail-modal');
        const body = document.getElementById('lead-detail-body');
        const title = document.getElementById('lead-detail-title');
        const subtitle = document.getElementById('lead-detail-subtitle');

        if (!modal || !body) return;

        modal.dataset.leadId = String(leadId);
        this.resetLeadDetailToolbar();
        title.textContent = 'Carregando...';
        subtitle.textContent = '';
        body.innerHTML =
            '<div class="flex justify-center py-16"><div class="spinner h-10 w-10 rounded-full border-2 border-slate-200 border-t-primary-600"></div></div>';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        try {
            const [leadRes, evRes] = await Promise.all([API.leads.get(leadId), API.leads.getEvents(leadId)]);
            if (!leadRes.success || !leadRes.data?.lead) {
                body.innerHTML =
                    '<div class="p-6"><p class="text-sm text-red-600">Lead nao encontrado.</p></div>';
                return;
            }
            const lead = leadRes.data.lead;
            const events = evRes.success && evRes.data?.events ? evRes.data.events : [];

            title.textContent = lead.name;
            subtitle.textContent = [lead.pipeline_name, lead.stage_name].filter(Boolean).join(' · ') || '';

            this.setupLeadDetailToolbar(lead);
            body.innerHTML = this.buildLeadDetailHtml(lead, events);
            this.initLeadDetailTabs(body);
            this.initLeadDetailFollowUp(body, lead);
        } catch (error) {
            console.error(error);
            body.innerHTML = '<div class="p-6"><p class="text-sm text-red-600">Erro ao carregar detalhes.</p></div>';
        }
    },

    initLeadDetailTabs(bodyEl) {
        const tabs = bodyEl.querySelectorAll('[data-detail-tab]');
        const panels = bodyEl.querySelectorAll('[data-detail-panel]');
        if (!tabs.length || !panels.length) return;

        const activate = (name) => {
            tabs.forEach((t) => {
                const on = t.getAttribute('data-detail-tab') === name;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.classList.toggle('bg-white', on);
                t.classList.toggle('text-primary-800', on);
                t.classList.toggle('shadow-sm', on);
                t.classList.toggle('text-slate-600', !on);
            });
            panels.forEach((p) => {
                const show = p.getAttribute('data-detail-panel') === name;
                p.classList.toggle('hidden', !show);
            });
        };

        tabs.forEach((t) => {
            t.addEventListener('click', () => activate(t.getAttribute('data-detail-tab')));
        });

        const tabTriggers = bodyEl.querySelectorAll('[data-tab-trigger]');
        tabTriggers.forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-tab-trigger');
                if (target) activate(target);
            });
        });

        activate('summary');
    },

    async refreshLeadDetailEvents(leadId, bodyEl) {
        const evRes = await API.leads.getEvents(leadId);
        if (!evRes.success || !evRes.data?.events) return;
        const events = evRes.data.events;
        const hist = bodyEl.querySelector('[data-detail-panel="history"]');
        if (!hist) return;
        const intro = `<p class="mb-4 text-sm leading-relaxed text-slate-600">Linha do tempo com mudancas de etapa, importacoes, notas e interacoes. Passagens por <strong>ganho</strong> ou <strong>perda</strong> aparecem como eventos separados.</p>`;
        hist.innerHTML = `${intro}${this.buildEventTimeline(events)}`;
    },

    initLeadDetailFollowUp(bodyEl, lead) {
        const leadId = lead.id;
        const applyVars = (text) =>
            String(text || '')
                .replace(/\{nome\}/g, lead.name || '')
                .replace(/\{produto\}/g, lead.product_interest || 'nosso produto');

        const tplSelect = bodyEl.querySelector('#lead-wa-template');
        const msgTa = bodyEl.querySelector('#lead-wa-message');
        const btnSend = bodyEl.querySelector('#lead-wa-send');

        const fillFromTemplate = (tpl) => {
            if (!msgTa || !tpl) return;
            msgTa.value = applyVars(tpl.content);
        };

        if (tplSelect) {
            // Usa a nova API que retorna templates filtrados por pipeline/stage do lead
            API.leads.getTemplates(leadId).then((res) => {
                if (!res.success) return;
                const list = res.data.templates || [];
                const st = lead.stage_type || '';
                
                // Ordena por position (cadencia)
                list.sort((a, b) => (parseInt(a.position, 10) || 1) - (parseInt(b.position, 10) || 1));
                
                tplSelect.innerHTML =
                    '<option value="">(sem template — mensagem vazia)</option>';
                list.forEach((t) => {
                    const o = document.createElement('option');
                    o.value = String(t.id);
                    const pos = parseInt(t.position, 10) || 1;
                    // Mostra posicao na cadencia (ex: "1. Nome do Template")
                    o.textContent = `${pos}. ${t.name}`;
                    tplSelect.appendChild(o);
                });

                // Por padrao, nao seleciona nenhum template (mensagem vazia)
                // O usuario deve escolher ativamente se quer usar um template
                if (list.length > 0) {
                    tplSelect.value = '';  // Sem template por padrao
                    if (msgTa) msgTa.value = '';  // Mensagem vazia
                }

                tplSelect.addEventListener('change', () => {
                    const id = tplSelect.value;
                    if (!id) {
                        // Sem template selecionado - limpa mensagem
                        if (msgTa) msgTa.value = '';
                        return;
                    }
                    const t = list.find((x) => String(x.id) === id);
                    if (t) fillFromTemplate(t);
                });
            });
        }

        btnSend?.addEventListener('click', async () => {
            const customMsg = msgTa?.value?.trim();
            const tplId = tplSelect?.value ? parseInt(tplSelect.value, 10) : 0;
            const payload = {};
            if (tplId > 0) payload.template_id = tplId;
            if (customMsg) payload.message = customMsg;
            await this.runWhatsAppTrigger(leadId, payload);
            await this.refreshLeadDetailEvents(leadId, bodyEl);
        });

        // Carrega timeline de observacoes
        this.loadNotesTimeline(bodyEl, leadId);

        bodyEl.querySelector('#lead-quick-note-save')?.addEventListener('click', async () => {
            const n = bodyEl.querySelector('#lead-quick-note')?.value?.trim();
            if (!n) {
                App.toast('Escreva uma observacao', 'warning');
                return;
            }
            const btn = bodyEl.querySelector('#lead-quick-note-save');
            btn.disabled = true;
            btn.textContent = 'Enviando...';
            
            const r = await API.leads.addNote(leadId, n);
            
            btn.disabled = false;
            btn.textContent = 'Enviar';
            
            if (r.success) {
                App.toast('Observacao salva', 'success');
                const ta = bodyEl.querySelector('#lead-quick-note');
                if (ta) ta.value = '';
                // Recarrega apenas o timeline de observacoes
                await this.loadNotesTimeline(bodyEl, leadId);
                // Atualiza tambem o historico geral
                await this.refreshLeadDetailEvents(leadId, bodyEl);
            } else App.toast(r.message || 'Erro', 'error');
        });

        bodyEl.querySelector('#lead-followup-save')?.addEventListener('click', async () => {
            const kind = bodyEl.querySelector('#lead-followup-kind')?.value || 'other';
            const text = bodyEl.querySelector('#lead-followup-text')?.value?.trim();
            if (!text) {
                App.toast('Preencha os detalhes da acao', 'warning');
                return;
            }
            const r = await API.leads.logFollowup(leadId, { kind, text });
            if (r.success) {
                App.toast('Acao registrada', 'success');
                const ta = bodyEl.querySelector('#lead-followup-text');
                if (ta) ta.value = '';
                await this.refreshLeadDetailEvents(leadId, bodyEl);
            } else App.toast(r.message || 'Erro', 'error');
        });
    },

    async loadNotesTimeline(bodyEl, leadId) {
        const timelineEl = bodyEl.querySelector('#lead-notes-timeline');
        if (!timelineEl) return;

        try {
            const res = await API.leads.getEvents(leadId);
            if (!res.success || !res.data.events) {
                timelineEl.innerHTML = '<div class="text-center text-sm text-slate-400 py-4">Erro ao carregar observacoes</div>';
                return;
            }

            // Filtra apenas eventos de nota/observacao
            const notes = res.data.events.filter(e => 
                e.event_type === 'note_added' || 
                (e.metadata && e.metadata.followup_kind === 'note')
            );

            if (notes.length === 0) {
                timelineEl.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <div class="mb-2 rounded-full bg-violet-100 p-3">
                            <svg class="h-5 w-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </div>
                        <p class="text-sm text-slate-500">Nenhuma observacao registrada ainda</p>
                        <p class="mt-1 text-xs text-slate-400">As observacoes aparecerao aqui em ordem cronologica</p>
                    </div>
                `;
                return;
            }

            // Ordena do mais recente para o mais antigo
            const sortedNotes = [...notes].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            const html = sortedNotes.map((note, index) => {
                const date = new Date(note.created_at);
                const dateStr = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
                const timeStr = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                
                // Extrai contexto dos metadados se existir
                const meta = note.metadata || {};
                const pipeline = meta.pipeline_name || '';
                const stage = meta.stage_name || '';
                const product = meta.product_interest || '';
                
                // Mostra contexto apenas se existir
                const contextInfo = pipeline || stage || product 
                    ? `<div class="mt-1.5 flex flex-wrap gap-1 text-[10px]">
                        ${pipeline ? `<span class="rounded bg-violet-100 px-1.5 py-0.5 text-violet-700">${this.escapeHtml(pipeline)}</span>` : ''}
                        ${stage ? `<span class="rounded bg-violet-100 px-1.5 py-0.5 text-violet-700">${this.escapeHtml(stage)}</span>` : ''}
                        ${product ? `<span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-700">${this.escapeHtml(product)}</span>` : ''}
                       </div>` 
                    : '';
                
                const isFirst = index === 0;
                
                return `
                    <div class="group mb-4 ${isFirst ? 'animate-pulse-once' : ''}">
                        <div class="flex items-start gap-3">
                            <div class="flex flex-col items-center">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                ${index < sortedNotes.length - 1 ? '<div class="mt-1 h-full w-px bg-violet-200 min-h-[20px]"></div>' : ''}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="rounded-2xl rounded-tl-sm bg-violet-50 px-4 py-3 shadow-sm ring-1 ring-violet-100">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-violet-900">${this.escapeHtml(note.user_name || 'Usuario')}</span>
                                        <div class="flex items-center gap-1.5 text-[10px] text-slate-500">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>${dateStr} as ${timeStr}</span>
                                        </div>
                                    </div>
                                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">${this.escapeHtml(note.description || '')}</p>
                                    ${contextInfo}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            timelineEl.innerHTML = html;
            
            // Scroll para o topo (observacao mais recente)
            timelineEl.scrollTop = 0;
            
        } catch (e) {
            console.error('Erro ao carregar timeline de observacoes:', e);
            timelineEl.innerHTML = '<div class="text-center text-sm text-slate-400 py-4">Erro ao carregar observacoes</div>';
        }
    },

    buildLeadDetailHtml(lead, events) {
        const statusBadge = () => {
            const s = lead.status || 'active';
            if (s === 'won')
                return '<span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Ganho</span>';
            if (s === 'lost')
                return '<span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Perdido</span>';
            return '<span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Ativo</span>';
        };

        const tags =
            Array.isArray(lead.tags) && lead.tags.length
                ? lead.tags
                      .map(
                          (t) =>
                              `<span class="mb-1 mr-1 inline-flex max-w-full items-center truncate rounded-full px-2.5 py-1 text-xs font-medium text-white shadow-sm" style="background:${t.color || '#6B7280'}">${this.escapeHtml(t.name)}</span>`
                      )
                      .join('')
                : '<span class="text-sm text-slate-400">Nenhuma tag</span>';

        const dash = '<span class="text-slate-400">—</span>';

        const textRow = (raw) => {
            if (raw === undefined || raw === null) return dash;
            const s = String(raw).trim();
            if (s === '') return dash;
            return this.escapeHtml(s);
        };

        const monoRow = (raw) => {
            if (!raw || !String(raw).trim()) return dash;
            return `<span class="font-mono text-[13px]">${this.escapeHtml(String(raw))}</span>`;
        };

        const row = (label, valueInnerHtml) => `
            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-baseline sm:gap-6">
                <div class="shrink-0 text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:w-44">${this.escapeHtml(label)}</div>
                <div class="min-w-0 flex-1 break-words text-sm leading-relaxed text-slate-900">${valueInnerHtml}</div>
            </div>`;

        const detailRows = [
            row('Valor', this.formatCurrency(lead.value)),
            row('Pipeline', textRow(lead.pipeline_name)),
            row('Etapa atual', textRow(lead.stage_name)),
            row('Responsavel', textRow(lead.assigned_user_name)),
            row('Telefone', monoRow(lead.phone)),
            row('E-mail', textRow(lead.email)),
            row('Origem', textRow(lead.source)),
            row('Produtos / interesse', textRow(lead.product_interest)),
            row('Criado em', lead.created_at ? this.formatDateTime(lead.created_at) : dash),
        ];

        if (lead.won_at) {
            detailRows.push(row('Marcado ganho em', this.formatDateTime(lead.won_at)));
        }
        if (lead.lost_at) {
            detailRows.push(row('Marcado perdido em', this.formatDateTime(lead.lost_at)));
        }
        if (lead.loss_reason) {
            detailRows.push(row('Motivo da perda', textRow(lead.loss_reason)));
        }

        const hasPhone =
            !!lead.phone_normalized ||
            (lead.phone && String(lead.phone).replace(/\D/g, '').length >= 8);

        const followUpWhatsapp = hasPhone
            ? `<div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 shadow-sm">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-900">WhatsApp</div>
                <p class="mt-1 text-xs leading-relaxed text-slate-600">
                    Selecione um template para preencher a mensagem automaticamente, 
                    ou deixe em branco para abrir o WhatsApp sem texto preenchido.
                    Use as variaveis {nome} e {produto} no conteudo dos templates.
                </p>
                <label class="mt-3 block text-xs font-medium text-slate-700" for="lead-wa-template">Template (opcional)</label>
                <select id="lead-wa-template" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></select>
                <label class="mt-3 block text-xs font-medium text-slate-700" for="lead-wa-message">Mensagem (opcional)</label>
                <textarea id="lead-wa-message" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-900" placeholder="Deixe em branco para abrir WhatsApp sem mensagem, ou digite uma mensagem personalizada"></textarea>
                <button type="button" id="lead-wa-send" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 sm:w-auto">
                    Abrir WhatsApp e registrar
                </button>
            </div>`
            : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-3 text-xs text-slate-600">Sem telefone valido — cadastre um numero para usar templates WhatsApp.</div>`;

        const followUpNote = `<div class="flex h-full flex-col rounded-2xl border border-violet-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-violet-100 bg-violet-50/50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-violet-900">Historico de Observacoes</span>
                    </div>
                    <span class="text-[10px] text-violet-600">Persistente em todas as etapas</span>
                </div>
                <div id="lead-notes-timeline" class="flex-1 overflow-y-auto p-4">
                    <div class="flex items-center justify-center py-8">
                        <div class="h-6 w-6 animate-spin rounded-full border-2 border-violet-200 border-t-violet-600"></div>
                    </div>
                </div>
                <div class="border-t border-violet-100 bg-violet-50/30 p-3">
                    <div class="flex gap-2">
                        <textarea id="lead-quick-note" rows="2" class="flex-1 resize-none rounded-lg border border-violet-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Digite uma observacao..."></textarea>
                        <button type="button" id="lead-quick-note-save" class="shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 active:scale-95">
                            Enviar
                        </button>
                    </div>
                    <p class="mt-1 text-[10px] text-violet-600">As observacoes ficam vinculadas a este lead em todas as etapas e pipelines</p>
                </div>
            </div>`;

        const followUpAction = `<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Registrar acao</div>
                <select id="lead-followup-kind" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="call">Ligacao</option>
                    <option value="email">E-mail</option>
                    <option value="meeting">Reuniao</option>
                    <option value="other">Outro</option>
                </select>
                <textarea id="lead-followup-text" rows="2" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Detalhes"></textarea>
                <button type="button" id="lead-followup-save" class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100">Registrar no historico</button>
            </div>`;

        const buildMiniNotesTimeline = () => {
            const notes = events.filter(
                (e) => e.event_type === 'note_added' || (e.metadata && e.metadata.followup_kind === 'note')
            );
            const recent = [...notes]
                .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
                .slice(0, 3);

            if (!recent.length) {
                return `<p class="py-2 text-sm text-slate-400">Nenhuma observacao registrada ainda.</p>`;
            }

            return recent
                .map((note) => {
                    const meta = note.metadata || {};
                    const dateText = note.created_at ? this.formatDateTime(note.created_at) : '—';
                    const message = String(note.description || '').trim();
                    const preview = message.length > 90 ? `${message.slice(0, 90)}...` : message;
                    const context = [meta.pipeline_name, meta.stage_name].filter(Boolean).join(' › ');
                    return `
                    <div class="border-b border-violet-50 py-2 last:border-b-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-slate-700">${this.escapeHtml(note.user_name || 'Usuario')}</span>
                            <span class="text-[10px] text-slate-400">${this.escapeHtml(dateText)}</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600">${this.escapeHtml(preview || 'Sem descricao')}</p>
                        ${
                            context
                                ? `<span class="mt-1 inline-block rounded bg-violet-50 px-1.5 py-0.5 text-[10px] text-violet-600">${this.escapeHtml(context)}</span>`
                                : ''
                        }
                    </div>
                `;
                })
                .join('');
        };

        // Aba Resumo - dados do lead + mini timeline de observacoes
        const summarySection = `
            <div data-detail-panel="summary" class="space-y-4 text-slate-900">
                <div class="flex flex-wrap items-center gap-2">
                    ${statusBadge()}
                    ${
                        lead.temperature
                            ? `<span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-900 ring-1 ring-amber-200/80">Temperatura: ${this.escapeHtml(lead.temperature)}</span>`
                            : ''
                    }
                </div>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
                    ${detailRows.join('')}
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tags</div>
                    <div class="mt-2 flex flex-wrap gap-1">${tags}</div>
                </div>
                ${
                    lead.notes_summary
                        ? `<div class="rounded-2xl border border-amber-200/80 bg-amber-50/80 p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-900">Resumo de notas</div>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">${this.escapeHtml(lead.notes_summary)}</p>
                </div>`
                        : ''
                }
                <div class="rounded-2xl border border-violet-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-violet-900">Ultimas observacoes</div>
                        <button type="button" data-tab-trigger="actions" class="text-[10px] font-medium text-violet-600 transition hover:text-violet-800 hover:underline">Ver todas →</button>
                    </div>
                    <div class="space-y-1">
                        ${buildMiniNotesTimeline()}
                    </div>
                </div>
            </div>
        `;

        // Nova aba Acoes - WhatsApp, observacoes e registro de acoes
        const actionsSection = `
            <div data-detail-panel="actions" class="hidden text-slate-900">
                <div class="grid gap-4 lg:grid-cols-5">
                    <div class="space-y-4 lg:col-span-2">
                        ${followUpWhatsapp}
                        ${followUpAction}
                    </div>
                    <div class="h-[520px] lg:col-span-3">
                        ${followUpNote}
                    </div>
                </div>
            </div>
        `;

        const historySection = `
            <div data-detail-panel="history" class="hidden text-slate-900">
                <p class="mb-4 text-sm leading-relaxed text-slate-600">Linha do tempo com mudancas de etapa, importacoes, notas e interacoes. Passagens por <strong>ganho</strong> ou <strong>perda</strong> aparecem como eventos separados.</p>
                ${this.buildEventTimeline(events)}
            </div>
        `;

        return `
            <div class="px-3 pb-4 pt-3 sm:px-4 sm:pb-5">
                <div class="sticky top-0 z-10 -mx-0.5 mb-3 flex gap-1 rounded-xl bg-slate-200/60 p-1 shadow-inner ring-1 ring-slate-200/80" role="tablist" aria-label="Secoes do lead">
                    <button type="button" role="tab" data-detail-tab="summary" class="flex-1 rounded-xl px-3 py-2.5 text-sm font-medium transition">Resumo</button>
                    <button type="button" role="tab" data-detail-tab="actions" class="flex-1 rounded-xl px-3 py-2.5 text-sm font-medium transition">Acoes</button>
                    <button type="button" role="tab" data-detail-tab="history" class="flex-1 rounded-xl px-3 py-2.5 text-sm font-medium transition">Historico</button>
                </div>
                ${summarySection}
                ${actionsSection}
                ${historySection}
            </div>`;
    },

    buildEventTimeline(events) {
        if (!events.length) {
            return '<p class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">Nenhum evento registrado ainda.</p>';
        }

        const typeLabel = (t) => {
            const map = {
                created: 'Criacao',
                updated: 'Atualizacao',
                stage_changed: 'Etapa',
                note_added: 'Nota',
                whatsapp_trigger: 'WhatsApp',
                import: 'Importacao',
                email_sent: 'E-mail',
                call_made: 'Ligacao',
                meeting_scheduled: 'Reuniao',
                assigned: 'Atribuicao',
                converted: 'Conversao',
                lost: 'Perda',
                deleted: 'Exclusao',
                restored: 'Restauracao',
            };
            return map[t] || t;
        };

        const list = [...events].reverse();

        const rows = list
            .map((ev, idx) => {
                const when = ev.created_at ? this.formatDateTime(ev.created_at) : '';
                const who = ev.user_name ? this.escapeHtml(ev.user_name) : 'Sistema';
                let dotClass = 'bg-primary-500';
                if (ev.event_type === 'stage_changed') {
                    if (ev.to_stage_type === 'won') dotClass = 'bg-emerald-500';
                    else if (ev.to_stage_type === 'lost') dotClass = 'bg-red-500';
                }                 else if (ev.event_type === 'note_added') dotClass = 'bg-violet-500';
                else if (ev.event_type === 'import') dotClass = 'bg-sky-500';
                else if (ev.event_type === 'whatsapp_trigger') dotClass = 'bg-emerald-500';

                let extra = '';
                if (ev.event_type === 'whatsapp_trigger' && ev.metadata) {
                    const m = ev.metadata;
                    const prev = m.message_preview ? this.escapeHtml(m.message_preview) : '';
                    const tn = m.template_name ? this.escapeHtml(m.template_name) : '';
                    extra = `<div class="mt-2 space-y-1 rounded-lg bg-emerald-50/80 px-3 py-2 text-xs leading-snug text-slate-800 ring-1 ring-emerald-100">${
                        tn ? `<div><span class="font-semibold text-emerald-900">Template:</span> ${tn}</div>` : ''
                    }${prev ? `<div class="whitespace-pre-wrap text-slate-700">${prev}</div>` : ''}</div>`;
                }
                if (ev.event_type === 'stage_changed' && ev.metadata) {
                    const m = ev.metadata;
                    const from = m.from_stage_name || '—';
                    const to = m.to_stage_name || '—';
                    extra = `<div class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-snug text-slate-700"><span class="text-slate-400">De</span> ${this.escapeHtml(from)} <span class="text-slate-400">para</span> ${this.escapeHtml(to)}</div>`;
                    if (ev.to_stage_type === 'won') {
                        extra += `<div class="mt-2"><span class="inline-flex rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200">Ganho nesta movimentacao</span></div>`;
                    } else if (ev.to_stage_type === 'lost') {
                        extra += `<div class="mt-2"><span class="inline-flex rounded-md bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-800 ring-1 ring-red-200">Perda nesta movimentacao</span></div>`;
                    }
                }
                const desc = ev.description
                    ? `<div class="mt-2 text-sm leading-relaxed text-slate-700">${this.escapeHtml(ev.description)}</div>`
                    : '';

                const line =
                    idx < list.length - 1
                        ? '<div class="w-0.5 flex-1 min-h-[1.25rem] bg-gradient-to-b from-slate-200 to-slate-100" aria-hidden="true"></div>'
                        : '';

                return `
                <div class="flex gap-3">
                    <div class="flex w-9 shrink-0 flex-col items-center pt-0.5">
                        <span class="h-3 w-3 shrink-0 rounded-full border-2 border-white ${dotClass} shadow-sm ring-1 ring-slate-300/60"></span>
                        ${line}
                    </div>
                    <div class="min-w-0 flex-1 pb-6 last:pb-2">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-primary-800">${typeLabel(ev.event_type)}</span>
                            <time class="shrink-0 text-xs tabular-nums text-slate-400">${when}</time>
                        </div>
                        <div class="mt-0.5 text-[11px] font-medium text-slate-500">${who}</div>
                        ${desc}
                        ${extra}
                    </div>
                </div>`;
            })
            .join('');

        return `<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">${rows}</div>`;
    },

    closeLeadDetail() {
        const modal = document.getElementById('lead-detail-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        this.resetLeadDetailToolbar();
    },

    formatDateTime(iso) {
        return typeof App !== 'undefined' && typeof App.formatDateTime === 'function'
            ? App.formatDateTime(iso)
            : (() => {
                  if (!iso) return '—';
                  const d = new Date(iso);
                  if (Number.isNaN(d.getTime())) return String(iso);
                  return d.toLocaleString('es-ES', {
                      timeZone: 'Europe/Madrid',
                      dateStyle: 'short',
                      timeStyle: 'short',
                  });
              })();
    },

    formatCurrency(value) {
        return typeof App !== 'undefined' && typeof App.formatCurrency === 'function'
            ? App.formatCurrency(value)
            : new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value) || 0);
    },

    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.kanban-page')) {
        Kanban.init();
    }
});

window.Kanban = Kanban;
